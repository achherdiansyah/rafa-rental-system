<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceDetail>
 */
class InvoiceDetailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'description' => 'Sewa Excavator PC200-8 (7 Hari)',
            'unit_price' => 2000000.00,
            'quantity' => 7.00,
            'subtotal' => 14000000.00,
        ];
    }
}
