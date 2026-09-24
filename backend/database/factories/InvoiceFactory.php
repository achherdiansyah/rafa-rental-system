<?php

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Booking;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV/'.now()->format('Ymd').'/'.fake()->unique()->numerify('####'),
            'booking_id' => Booking::factory(),
            'due_at' => now()->addHours(24),
            'status' => InvoiceStatus::UNPAID->value,
            'subtotal' => 15000000.00,
            'tax_total' => 0.00,
            'grand_total' => 15000000.00,
            'paid_amount' => 0.00,
            'overpayment_amount' => 0.00,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PAID->value,
            'paid_amount' => $attributes['grand_total'],
        ]);
    }

    public function partiallyPaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::PARTIALLY_PAID->value,
            'paid_amount' => 5000000.00,
        ]);
    }

    public function overpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::OVERPAID->value,
            'paid_amount' => 16000000.00,
            'overpayment_amount' => 1000000.00,
        ]);
    }
}
