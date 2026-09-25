<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\BookingPricingResult;
use App\DTOs\Pricing\LineItemPricingInput;
use App\DTOs\Pricing\LineItemPricingResult;
use App\Exceptions\BusinessRuleException;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;

class PricingCalculatorService
{
    /**
     * Standard minimum daily work hours as defined in PRD V3.
     */
    public const STANDARD_DAILY_HOURS = 8;

    /**
     * Calculate price for a single equipment line item.
     *
     *
     * @throws BusinessRuleException
     */
    public function calculateLineItem(LineItemPricingInput $input): LineItemPricingResult
    {
        /** @var EquipmentModel|null $model */
        $model = EquipmentModel::find($input->equipmentModelId);
        if (! $model || ! $model->is_active) {
            throw new BusinessRuleException(
                "Model peralatan ID #{$input->equipmentModelId} tidak ditemukan atau sedang tidak aktif."
            );
        }

        // Find active pricing matching the All-in / Non All-in scheme
        /** @var EquipmentPrice|null $price */
        $price = EquipmentPrice::where('equipment_model_id', $input->equipmentModelId)
            ->where('is_all_in', $input->isAllIn)
            ->where('effective_date', '<=', $input->startDate->toDateString())
            ->latest('effective_date')
            ->first();

        if (! $price) {
            $schemeName = $input->isAllIn ? 'All-in' : 'Non All-in';
            throw new BusinessRuleException(
                "Master tarif sewa untuk model {$model->brand} {$model->model_name} dengan skema {$schemeName} belum ditetapkan."
            );
        }

        // Calculate calendar duration in days inclusive (minimum 1 day)
        $durationDays = max(1, $input->startDate->diffInDays($input->endDate) + 1);

        // Daily rate based on standard 8 hours minimum rule
        $hourlyRate = (float) $price->base_rate;
        $dailyRate = $hourlyRate * self::STANDARD_DAILY_HOURS;

        // Rental subtotal = dailyRate * durationDays * physical quantity
        $rentalSubtotal = $dailyRate * $durationDays * $input->quantity;

        // MOB & DEMOB are calculated strictly per physical unit
        $mobSubtotal = $input->mobRatePerUnit * $input->quantity;
        $demobSubtotal = $input->demobRatePerUnit * $input->quantity;

        // Total per line item (Zero extra attachment charge, zero tax, zero discount)
        $lineTotal = $rentalSubtotal + $mobSubtotal + $demobSubtotal;

        return new LineItemPricingResult(
            equipmentModelId: $model->id,
            modelName: "{$model->brand} {$model->model_name}",
            quantity: $input->quantity,
            durationDays: $durationDays,
            isAllIn: $input->isAllIn,
            hourlyRate: $hourlyRate,
            dailyRate: $dailyRate,
            rentalSubtotal: $rentalSubtotal,
            mobRatePerUnit: $input->mobRatePerUnit,
            mobSubtotal: $mobSubtotal,
            demobRatePerUnit: $input->demobRatePerUnit,
            demobSubtotal: $demobSubtotal,
            lineTotal: $lineTotal,
        );
    }

    /**
     * Calculate aggregate booking price across multiple equipment line items.
     *
     * @param  LineItemPricingInput[]  $items
     *
     * @throws BusinessRuleException
     */
    public function calculateBooking(array $items): BookingPricingResult
    {
        if (empty($items)) {
            throw new BusinessRuleException('Rincian item alat berat tidak boleh kosong untuk perhitungan harga.');
        }

        $lineResults = [];
        $totalRental = 0.00;
        $totalMob = 0.00;
        $totalDemob = 0.00;

        foreach ($items as $itemInput) {
            $lineResult = $this->calculateLineItem($itemInput);
            $lineResults[] = $lineResult;

            $totalRental += $lineResult->rentalSubtotal;
            $totalMob += $lineResult->mobSubtotal;
            $totalDemob += $lineResult->demobSubtotal;
        }

        // Strict Phase 1 Rule: No tax, No discount, No unintended fees
        $taxAmount = 0.00;
        $discountAmount = 0.00;
        $grandTotal = $totalRental + $totalMob + $totalDemob;

        return new BookingPricingResult(
            items: $lineResults,
            totalRentalAmount: $totalRental,
            totalMobAmount: $totalMob,
            totalDemobAmount: $totalDemob,
            taxAmount: $taxAmount,
            discountAmount: $discountAmount,
            grandTotal: $grandTotal,
        );
    }
}
