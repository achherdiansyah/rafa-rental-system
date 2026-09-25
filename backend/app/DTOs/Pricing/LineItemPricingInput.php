<?php

namespace App\DTOs\Pricing;

use Carbon\Carbon;

readonly class LineItemPricingInput
{
    public function __construct(
        public int $equipmentModelId,
        public int $quantity,
        public Carbon $startDate,
        public Carbon $endDate,
        public bool $isAllIn,
        public float $mobRatePerUnit = 0.00,
        public float $demobRatePerUnit = 0.00,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            equipmentModelId: (int) $data['equipment_model_id'],
            quantity: max(1, (int) ($data['quantity'] ?? 1)),
            startDate: Carbon::parse($data['start_date'])->startOfDay(),
            endDate: Carbon::parse($data['end_date'])->startOfDay(),
            isAllIn: (bool) ($data['is_all_in'] ?? false),
            mobRatePerUnit: (float) ($data['mob_rate_per_unit'] ?? 0.00),
            demobRatePerUnit: (float) ($data['demob_rate_per_unit'] ?? 0.00),
        );
    }
}
