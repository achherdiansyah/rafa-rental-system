<?php

namespace Tests\Feature\Dashboard;

use App\Models\Booking;
use App\Models\CustomerProfile;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_consolidated_dashboard_summary(): void
    {
        $user = User::factory()->create();
        CustomerProfile::factory()->create(['user_id' => $user->id, 'verification_status' => 'VERIFIED']);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        $rental = Rental::factory()->create(['booking_id' => $booking->id]);
        $invoice = Invoice::factory()->create(['booking_id' => $booking->id]);

        Sanctum::actingAs($user);
        $res = $this->getJson('/api/v1/dashboard/summary');

        $res->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'bookings',
                    'rentals',
                    'invoices',
                    'project_locations',
                    'notifications',
                ],
            ]);

        $this->assertCount(1, $res->json('data.bookings'));
        $this->assertCount(1, $res->json('data.rentals'));
        $this->assertCount(1, $res->json('data.invoices'));
        $this->assertCount(1, $res->json('data.project_locations'));
    }
}