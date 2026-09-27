<?php

namespace App\DTOs\Billing;

final readonly class BillingLine
{
    public function __construct(
        public int $rentalDetailId,
        public string $unitSerial,
        public string $modelName,
        public float $hourlyRate,
        public bool $isAllIn,
        public float $workHours,
        public float $workAmount,
        public float $mobCost,
        public float $demobCost,
        public float $lineTotal,
    ) {}
}
