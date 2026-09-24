<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Invoice;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_seeder_populates_required_development_records(): void
    {
        $this->seed(DatabaseSeeder::class);

        // 1. Admin & Owner
        $this->assertDatabaseHas('users', ['email' => 'admin@rafarental.com', 'role' => UserRole::ADMIN->value]);
        $this->assertDatabaseHas('users', ['email' => 'owner@rafarental.com', 'role' => UserRole::OWNER->value]);

        // 2. Customers
        $this->assertDatabaseHas('users', ['email' => 'budi@kontraktor.com', 'role' => UserRole::USER->value]);
        $this->assertDatabaseHas('customer_profiles', ['company_name' => 'PT Maju Konstruksi Jaya']);

        // 3. Equipment & Units
        $this->assertDatabaseHas('equipment_types', ['name' => 'Excavator']);
        $this->assertDatabaseHas('equipment_models', ['model_name' => 'PC200-8']);
        $this->assertDatabaseHas('equipment_units', ['serial_number' => 'KM-PC200-001']);

        // 4. Bank Account
        $this->assertDatabaseHas('bank_accounts', ['bank_name' => 'BCA', 'account_number' => '1234567890']);

        // 5. Sample Booking & Invoice
        $this->assertDatabaseHas('bookings', ['booking_code' => 'RFA-BKG-20260901-0001']);
        $this->assertDatabaseHas('invoices', ['invoice_number' => 'INV/20260901/0001']);
        $this->assertDatabaseHas('payments', ['amount' => 7000000.00]);
    }
}
