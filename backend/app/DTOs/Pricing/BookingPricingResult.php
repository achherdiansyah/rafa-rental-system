<?php

namespace App\DTOs\Pricing;

readonly class BookingPricingResult
{
    /**
     * @param  LineItemPricingResult[]  $items
     */
    public function __construct(
        public array $items,
        public float $totalRentalAmount,
        public float $totalMobAmount,
        public float $totalDemobAmount,
        public float $taxAmount,
        public float $discountAmount,
        public float $grandTotal,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn (LineItemPricingResult $item) => $item->toArray(), $this->items),
            'total_rental_amount' => $this->totalRentalAmount,
            'total_mob_amount' => $this->totalMobAmount,
            'total_demob_amount' => $this->totalDemobAmount,
            'tax_amount' => $this->taxAmount,
            'discount_amount' => $this->discountAmount,
            'grand_total' => $this->grandTotal,
        ];
    }
}
