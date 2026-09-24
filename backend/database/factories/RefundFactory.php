<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Invoice;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'amount' => 1000000.00,
            'reason' => 'OVERPAYMENT',
            'customer_bank_info' => 'BCA 1234567890 a/n Pelanggan',
            'status' => RefundStatus::REQUESTED->value,
            'processed_by' => null,
            'processed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::COMPLETED->value,
            'processed_by' => User::factory()->admin(),
            'processed_at' => now(),
        ]);
    }
}
