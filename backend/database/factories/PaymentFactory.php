<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'bank_account_id' => BankAccount::factory(),
            'payment_date' => now(),
            'amount' => 5000000.00,
            'status' => PaymentStatus::PENDING->value,
            'rejection_reason' => null,
            'verified_by' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::SUBMITTED->value,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::APPROVED->value,
            'verified_by' => User::factory()->admin(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::REJECTED->value,
            'rejection_reason' => 'Mutasi rekening tidak ditemukan',
            'verified_by' => User::factory()->admin(),
        ]);
    }
}
