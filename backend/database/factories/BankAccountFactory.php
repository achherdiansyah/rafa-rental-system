<?php

namespace Database\Factories;

use App\Models\BankAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'bank_name' => fake()->randomElement(['BCA', 'Mandiri', 'BNI', 'BRI']),
            'account_number' => fake()->unique()->numerify('##########'),
            'account_name' => 'PT RAFA RENTAL NUSANTARA',
            'is_active' => true,
        ];
    }
}
