<?php

namespace App\Actions\Invoice;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Exceptions\BusinessRuleException;
use App\Models\BookingDetail;
use App\Models\EquipmentPrice;
use App\Models\Invoice;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\Billing\InvoiceNumberGenerator;
use App\Support\AuditLogger;

class CreateDailyInvoiceAction
{
    public const STANDARD_HOURS = 8;

    public function __construct(
        private readonly InvoiceNumberGenerator $numberGenerator
    ) {}

    public function execute(User $admin, Timesheet $timesheet): Invoice
    {
        $timesheet->loadMissing([
            'rentalDetail.assignment.detail.model',
            'rentalDetail.rental.booking',
        ]);

        $rentalDetail = $timesheet->rentalDetail;
        $booking = $rentalDetail?->rental?->booking;

        if (! $booking) {
            throw new BusinessRuleException('Booking terkait tidak ditemukan.');
        }

        // Check duplicate: 1 timesheet = 1 daily invoice
        $existingDailyInvoice = Invoice::where('timesheet_id', $timesheet->id)->exists();

        if ($existingDailyInvoice) {
            throw new BusinessRuleException('Daily invoice untuk timesheet ini sudah diterbitkan.');
        }

        // 3-day outstanding check per unit (rental_detail)
        $maxOutstanding = (int) config('availability.max_outstanding_days', 3);
        $unpaidDailyCount = Invoice::where('invoice_type', InvoiceType::DAILY_WORK)
            ->whereIn('status', [
                InvoiceStatus::ISSUED,
                InvoiceStatus::UNPAID,
                InvoiceStatus::PARTIALLY_PAID,
                InvoiceStatus::OVERDUE,
            ])
            ->whereHas('timesheet', function ($q) use ($rentalDetail) {
                $q->where('rental_detail_id', $rentalDetail->id);
            })
            ->count();

        if ($unpaidDailyCount >= $maxOutstanding) {
            throw new BusinessRuleException(
                "Melebihi batas outstanding harian ({$maxOutstanding} hari). Selesaikan tagihan sebelumnya."
            );
        }

        /** @var BookingDetail|null $bookingDetail */
        $bookingDetail = $rentalDetail->assignment?->detail;

        $baseRate = (float) ($bookingDetail?->rental_rate_snapshot ?? 0);
        $overtimeRate = $this->resolveOvertimeRate($bookingDetail, $rentalDetail);
        $isAllIn = (bool) ($bookingDetail?->is_all_in ?? false);
        $workHours = (float) $timesheet->total_work_hours;
        $modelName = $bookingDetail?->model?->model_name ?? $rentalDetail->assignment?->unit?->model?->model_name ?? 'Alat Berat';

        // Normal / overtime split
        $normalHours = min($workHours, self::STANDARD_HOURS);
        $overtimeHours = max(0, $workHours - self::STANDARD_HOURS);

        $normalAmount = round($normalHours * $baseRate, 2);
        $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
        $grandTotal = $normalAmount + $overtimeAmount;

        $issuedAt = now();
        $dueAt = $issuedAt->copy()->addHours(24);

        $invoice = Invoice::create([
            'invoice_number' => $this->numberGenerator->generate(),
            'booking_id' => $booking->id,
            'timesheet_id' => $timesheet->id,
            'invoice_type' => InvoiceType::DAILY_WORK,
            'status' => InvoiceStatus::ISSUED,
            'issued_at' => $issuedAt,
            'due_at' => $dueAt,
            'subtotal' => $grandTotal,
            'tax_total' => 0.00,
            'grand_total' => $grandTotal,
            'paid_amount' => 0.00,
            'overpayment_amount' => 0.00,
        ]);

        $allInLabel = $isAllIn ? 'All-in' : 'Non All-in';
        $dateLabel = $timesheet->report_date->format('Y-m-d');
        $rateFmt = number_format($baseRate, 0, ',', '.');

        $invoice->details()->create([
            'description' => sprintf(
                'Sewa Harian %s (%s) — %s jam @ Rp %s [%s]',
                $modelName, $allInLabel, number_format($normalHours, 2), $rateFmt, $dateLabel
            ),
            'unit_price' => $baseRate,
            'quantity' => $normalHours,
            'subtotal' => $normalAmount,
        ]);

        if ($overtimeHours > 0) {
            $otRateFmt = number_format($overtimeRate, 0, ',', '.');
            $invoice->details()->create([
                'description' => sprintf(
                    'Lembur %s (%s) — %s jam @ Rp %s [%s]',
                    $modelName, $allInLabel, number_format($overtimeHours, 2), $otRateFmt, $dateLabel
                ),
                'unit_price' => $overtimeRate,
                'quantity' => $overtimeHours,
                'subtotal' => $overtimeAmount,
            ]);
        }

        AuditLogger::log('DAILY_INVOICE_CREATED', $invoice, [], [
            'timesheet_id' => $timesheet->id,
            'report_date' => $dateLabel,
            'work_hours' => $workHours,
            'normal_hours' => $normalHours,
            'overtime_hours' => $overtimeHours,
            'base_rate' => $baseRate,
            'overtime_rate' => $overtimeRate,
            'grand_total' => $grandTotal,
            'created_by' => $admin->id,
        ]);

        return $invoice;
    }

    public function updateForTimesheet(User $admin, Timesheet $timesheet): ?Invoice
    {
        $timesheet->loadMissing([
            'rentalDetail.assignment.detail.model',
            'rentalDetail.rental.booking',
        ]);

        $booking = $timesheet->rentalDetail?->rental?->booking;
        if (! $booking) {
            return null;
        }

        $dateLabel = $timesheet->report_date->format('Y-m-d');

        /** @var Invoice|null $invoice */
        $invoice = Invoice::where('timesheet_id', $timesheet->id)->first();

        if (! $invoice || in_array($invoice->status, [InvoiceStatus::PAID, InvoiceStatus::PARTIALLY_PAID])) {
            return null;
        }

        $bookingDetail = $timesheet->rentalDetail->assignment?->detail;
        $baseRate = (float) ($bookingDetail?->rental_rate_snapshot ?? 0);
        $overtimeRate = $this->resolveOvertimeRate($bookingDetail, $timesheet->rentalDetail);
        $isAllIn = (bool) ($bookingDetail?->is_all_in ?? false);
        $workHours = (float) $timesheet->total_work_hours;
        $modelName = $bookingDetail?->model?->model_name ?? 'Alat Berat';

        $normalHours = min($workHours, self::STANDARD_HOURS);
        $overtimeHours = max(0, $workHours - self::STANDARD_HOURS);

        $normalAmount = round($normalHours * $baseRate, 2);
        $overtimeAmount = round($overtimeHours * $overtimeRate, 2);
        $grandTotal = $normalAmount + $overtimeAmount;

        $allInLabel = $isAllIn ? 'All-in' : 'Non All-in';
        $rateFmt = number_format($baseRate, 0, ',', '.');

        $invoice->details()->delete();

        $invoice->details()->create([
            'description' => sprintf(
                'Sewa Harian %s (%s) — %s jam @ Rp %s [%s]',
                $modelName, $allInLabel, number_format($normalHours, 2), $rateFmt, $dateLabel
            ),
            'unit_price' => $baseRate,
            'quantity' => $normalHours,
            'subtotal' => $normalAmount,
        ]);

        if ($overtimeHours > 0) {
            $otRateFmt = number_format($overtimeRate, 0, ',', '.');
            $invoice->details()->create([
                'description' => sprintf(
                    'Lembur %s (%s) — %s jam @ Rp %s [%s]',
                    $modelName, $allInLabel, number_format($overtimeHours, 2), $otRateFmt, $dateLabel
                ),
                'unit_price' => $overtimeRate,
                'quantity' => $overtimeHours,
                'subtotal' => $overtimeAmount,
            ]);
        }

        $invoice->update([
            'subtotal' => $grandTotal,
            'grand_total' => $grandTotal,
        ]);

        AuditLogger::log('DAILY_INVOICE_UPDATED', $invoice, [], [
            'timesheet_id' => $timesheet->id,
            'report_date' => $dateLabel,
            'work_hours' => $workHours,
            'grand_total' => $grandTotal,
            'revised_by' => $admin->id,
        ]);

        return $invoice;
    }

    private function resolveOvertimeRate(?BookingDetail $bookingDetail, $rentalDetail): float
    {
        // 1. Snapshot on booking detail
        if ($bookingDetail && $bookingDetail->overtime_rate_snapshot !== null && (float) $bookingDetail->overtime_rate_snapshot > 0) {
            return (float) $bookingDetail->overtime_rate_snapshot;
        }

        // 2. Master data
        $modelId = $bookingDetail?->equipment_model_id ?? $rentalDetail->assignment?->unit?->equipment_model_id;
        $isAllIn = $bookingDetail?->is_all_in ?? false;

        if ($modelId) {
            $price = EquipmentPrice::where('equipment_model_id', $modelId)
                ->where('is_all_in', $isAllIn)
                ->latest('effective_date')
                ->first();

            if ($price && (float) $price->overtime_rate > 0) {
                return (float) $price->overtime_rate;
            }
        }

        // 3. Fallback to base rate
        return (float) ($bookingDetail?->rental_rate_snapshot ?? 0);
    }
}
