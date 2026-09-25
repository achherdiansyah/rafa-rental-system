<?php

namespace App\DTOs\Pricing;

readonly class LineItemPricingResult
{
    public function __construct(
        public int $equipmentModelId,
        public string $modelName,
        public int $quantity,
        public int $durationDays,
        public bool $isAllIn,
        public float $hourlyRate,
        public float $dailyRate,
        public float $rentalSubtotal,
        public float $mobRatePerUnit,
        public float $mobSubtotal,
        public float $demobRatePerUnit,
        public float $demobSubtotal,
        public float $lineTotal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'equipment_model_id' => $this->equipmentModelId,
            'model_name' => $this->modelName,
            'quantity' => $this->quantity,
            'duration_days' => $this->durationDays,
            'is_all_in' => $this->isAllIn,
            'hourly_rate' => $this->hourlyRate,
            'daily_rate' => $this->dailyRate,
            'rental_subtotal' => $this->rentalSubtotal,
            'mob_rate_per_unit' => $this->mobRatePerUnit,
            'mob_subtotal' => $this->mobSubtotal,
            'demob_rate_per_unit' => $this->demobRatePerUnit,
            'demob_subtotal' => $this->demobSubtotal,
            'line_total' => $this->lineTotal,
        ];
    }
}
