<?php

namespace App\Services\Billing;

use App\DTOs\Billing\BillingLine;
use App\DTOs\Billing\RentalBill;
use App\Enums\TimesheetStatus;
use App\Models\BookingDetail;
use App\Models\EquipmentPrice;
use App\Models\Rental;
use App\Models\RentalDetail;

/**
 * Billing engine (DAILY WORK = actual hours x applicable hourly rate).
 *
 * Rules enforced here (PRD V3 10-pricing-and-billing-rules):
 * - hourly rate basis, actual working hours from APPROVED timesheets
 *   (timesheet.total_work_hours = end_hm - start_hm - break), no rounding.
 * - All-in / Non All-in scheme carried per equipment line (no rate mutation).
 * - no tax, no discount, no overtime tariff.
 * - MOB/DEMOB charged per physical unit (each rental_detail maps 1:1 to a
 *   physical assignment); MOB and DEMOB use separate snapshot costs.
 * - historical price via snapshot copy (rental_rate_snapshot /
 *   mob_cost_snapshot / demob_cost_snapshot), falling back to the current
 *   master price only for legacy rows that predate snapshots.
 *
 * ponytail: daily-work cap / overtime tariff for actual hours > 8 is a pending
 * business decision (PBD) and intentionally NOT implemented here. When the
 * rule is decided, add it in a pricing layer, not in this engine.
 */
class RentalBillingService
{
    public function generate(Rental $rental): RentalBill
    {
        $rental->loadMissing([
            'booking.projectLocation',
            'details.assignment.unit.model',
            'details.assignment.detail',
        ]);

        $lines = [];
        $totalWorkHours = 0.00;
        $totalRental = 0.00;
        $totalMob = 0.00;
        $totalDemob = 0.00;

        foreach ($rental->details as $detail) {
            /** @var BookingDetail|null */
            $bookingDetail = $detail->assignment?->detail;

            $line = $this->buildLine($detail, $bookingDetail);
            $lines[] = $line;

            $totalWorkHours = $totalWorkHours + $line->workHours;
            $totalRental = $totalRental + $line->workAmount;
            $totalMob = $totalMob + $line->mobCost;
            $totalDemob = $totalDemob + $line->demobCost;
        }

        return new RentalBill(
            rentalId: $rental->id,
            bookingId: $rental->booking_id,
            modelName: $rental->booking?->projectLocation?->project_name ?? '',
            lines: $lines,
            totalWorkHours: $totalWorkHours,
            totalRentalAmount: $totalRental,
            totalMobAmount: $totalMob,
            totalDemobAmount: $totalDemob,
            grandTotal: $totalRental + $totalMob + $totalDemob,
        );
    }

    public function buildLine(RentalDetail $detail, ?BookingDetail $bookingDetail): BillingLine
    {
        $hourlyRate = $this->applicableHourlyRate($bookingDetail, $detail);
        [$mobCost, $demobCost] = $this->applicableMobDemob($bookingDetail, $detail);

        $workHours = $this->actualWorkingHours($detail);
        $workAmount = $workHours * $hourlyRate;

        $unit = $detail->assignment?->unit;

        return new BillingLine(
            rentalDetailId: $detail->id,
            unitSerial: $unit?->serial_number ?? '-',
            modelName: $unit?->model?->model_name ?? $bookingDetail?->model?->model_name ?? '-',
            hourlyRate: $hourlyRate,
            isAllIn: $bookingDetail?->is_all_in ?? false,
            workHours: $workHours,
            workAmount: $workAmount,
            mobCost: $mobCost,
            demobCost: $demobCost,
            lineTotal: $workAmount + $mobCost + $demobCost,
        );
    }

    public function actualWorkingHours(RentalDetail $detail): float
    {
        return (float) $detail->timesheets()
            ->where('status', TimesheetStatus::APPROVED)
            ->sum('total_work_hours');
    }

    public function applicableHourlyRate(?BookingDetail $bookingDetail, RentalDetail $detail): float
    {
        if ($bookingDetail && $bookingDetail->rental_rate_snapshot !== null) {
            return (float) $bookingDetail->rental_rate_snapshot;
        }

        $price = EquipmentPrice::where('equipment_model_id', $detail->assignment?->unit?->equipment_model_id)
            ->where('is_all_in', $bookingDetail?->is_all_in ?? false)
            ->latest('effective_date')
            ->first();

        return $price ? (float) $price->base_rate : 0.00;
    }

    /**
     * @return array{0: float, 1: float}
     */
    public function applicableMobDemob(?BookingDetail $bookingDetail, RentalDetail $detail): array
    {
        if ($bookingDetail && $bookingDetail->mob_cost_snapshot !== null && $bookingDetail->demob_cost_snapshot !== null) {
            return [(float) $bookingDetail->mob_cost_snapshot, (float) $bookingDetail->demob_cost_snapshot];
        }

        $price = EquipmentPrice::where('equipment_model_id', $bookingDetail?->equipment_model_id ?? $detail->assignment?->unit?->equipment_model_id)
            ->where('is_all_in', $bookingDetail?->is_all_in ?? false)
            ->latest('effective_date')
            ->first();

        return [
            $price ? (float) $price->mob_cost : 0.00,
            $price ? (float) $price->demob_cost : 0.00,
        ];
    }
}
