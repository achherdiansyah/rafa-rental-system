<?php

namespace Tests\Feature\Performance;

use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\RentalStatus;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\Rental;
use App\Models\RentalDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_hot_query_indexes_present(): void
    {
        $checks = [
            ['timesheets', 'timesheets_status_report_date_idx'],
            ['timesheets', 'timesheets_rental_detail_date_unique'],
            ['invoices', 'invoices_status_created_idx'],
            ['invoices', 'invoices_due_at_idx'],
            ['payments', 'payments_status_payment_date_idx'],
            ['refunds', 'refunds_status_created_idx'],
            ['refunds', 'refunds_source_idx'],
            ['bookings', 'bookings_status_created_idx'],
            ['rentals', 'rentals_status_created_idx'],
        ];

        foreach ($checks as [$table, $index]) {
            $this->assertTrue(
                Schema::hasIndex($table, $index),
                "Index {$index} pada {$table} harus ada."
            );
        }
    }

    public function test_rental_listing_is_eager_loaded_no_n1(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $model = EquipmentModel::factory()->create(['is_active' => true]);

        for ($i = 0; $i < 5; $i++) {
            $booking = Booking::factory()->create(['user_id' => $user->id, 'status' => BookingStatus::CONFIRMED->value]);
            $bd = BookingDetail::factory()->create(['booking_id' => $booking->id, 'equipment_model_id' => $model->id, 'quantity' => 1]);
            $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::ASSIGNED->value]);
            $assignment = BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $bd->id, 'equipment_unit_id' => $unit->id,
                'status' => 'ASSIGNED', 'is_current' => true, 'assigned_by' => $admin->id,
            ]);
            $rental = Rental::factory()->create(['booking_id' => $booking->id, 'status' => RentalStatus::ONGOING->value]);
            RentalDetail::factory()->create(['rental_id' => $rental->id, 'assignment_id' => $assignment->id]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/rentals?per_page=50')->assertOk()->assertJsonPath('meta.total', 5);

        $this->assertLessThan(12, $queries, 'List rental + eager detail/unit/model harus terbatas (bukan N+1).');
    }

    public function test_read_paths_do_not_mutate_business_state(): void
    {
        $before = ['bookings' => Booking::count(), 'rentals' => Rental::count(), 'units' => EquipmentUnit::count()];

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/reports/dashboard')->assertOk();
        $this->getJson('/api/v1/reports/operational/rentals')->assertOk();
        $this->getJson('/api/v1/reports/financial/outstanding')->assertOk();

        $this->assertEquals($before, ['bookings' => Booking::count(), 'rentals' => Rental::count(), 'units' => EquipmentUnit::count()]);
    }
}
