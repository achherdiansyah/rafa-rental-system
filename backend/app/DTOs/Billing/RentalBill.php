<?php

namespace App\DTOs\Billing;

final readonly class RentalBill
{
    /**
     * @param  BillingLine[]  $lines
     */
    public function __construct(
        public int $rentalId,
        public int $bookingId,
        public string $modelName,
        public array $lines,
        public float $totalWorkHours,
        public float $totalRentalAmount,
        public float $totalMobAmount,
        public float $totalDemobAmount,
        public float $grandTotal,
    ) {}
}
