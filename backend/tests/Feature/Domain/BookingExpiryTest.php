<?php

namespace Tests\Feature\Domain;

use App\Actions\Booking\ExpireBookingAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Enums\UserRole;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use App\Services\Equipment\EquipmentAvailabilityService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function approvedBooking(array $overrides = []): Booking
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        return Booking::factory()->create(array_merge([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => BookingStatus::APPROVED,
            'approved_at' => now()->subHours(2),
            'payment_deadline_at' => now()->subHour(),
        ], $overrides));
    }

    private function attachAssignedSlot(Booking $booking, EquipmentUnit $unit, int $quantity = 1): void
    {
        $model = $unit->model;
        $detail = BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => $quantity,
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(12)->toDateString(),
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => User::factory()->admin()->create()->id,
        ]);
        $unit->update(['status' => EquipmentStatus::ASSIGNED]);
    }

    public function test_expiry_releases_assigned_slots_and_restores_availability(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);
        $unit2 = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);

        $booking = $this->approvedBooking();
        $this->attachAssignedSlot($booking, $unit);

        // Before expiry: 1 of 2 units is free in the booking window
        $service = app(EquipmentAvailabilityService::class);
        $start = Carbon::parse($booking->details->first()->start_date);
        $end = Carbon::parse($booking->details->first()->end_date);
        $this->assertEquals(1, $service->getAvailableUnitsCount($model, $start, $end));

        app(ExpireBookingAction::class)->execute($booking);

        // Booking EXPIRED
        $this->assertEquals(BookingStatus::EXPIRED, $booking->fresh()->status);

        // Slot released, history preserved
        $assignment = BookingUnitAssignment::where('equipment_unit_id', $unit->id)->first();
        $this->assertEquals(false, $assignment->fresh()->is_current);
        $this->assertEquals(AssignmentStatus::CANCELLED, $assignment->fresh()->status);

        // Unit back to AVAILABLE -> availability restored to 2
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
        $this->assertEquals(2, $service->getAvailableUnitsCount($model, $start, $end));
    }

    public function test_no_premature_expiry_within_deadline(): void
    {
        $booking = $this->approvedBooking(['payment_deadline_at' => now()->addHours(20)]);

        $this->expectException(InvalidStateTransitionException::class);
        app(ExpireBookingAction::class)->execute($booking);
    }

    public function test_deadline_boundary_not_passed_at_exact_deadline(): void
    {
        // Just past the boundary instant to avoid clock-drift falsy positives;
        // a deadline that is equal-or-ahead of now must NOT expire.
        $booking = $this->approvedBooking(['payment_deadline_at' => now()->addSecond()]);

        try {
            app(ExpireBookingAction::class)->execute($booking);
            $this->fail('Expected InvalidStateTransitionException');
        } catch (InvalidStateTransitionException $e) {
            $this->assertStringContainsString('belum terlewati', $e->getMessage());
        }

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_booking_with_future_deadline_is_untouched_by_scheduler(): void
    {
        $this->approvedBooking(['payment_deadline_at' => now()->addHours(12)]);
        $this->approvedBooking(['payment_deadline_at' => now()->addDays(2)]);

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertEquals(0, Booking::where('status', BookingStatus::EXPIRED)->count());
    }

    public function test_scheduler_expires_overdue_bookings_and_releases_slots(): void
    {
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $model->id, 'status' => EquipmentStatus::AVAILABLE]);

        $overdue = $this->approvedBooking(['payment_deadline_at' => now()->subMinutes(30)]);
        $this->attachAssignedSlot($overdue, $unit);

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertEquals(BookingStatus::EXPIRED, $overdue->fresh()->status);
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }

    public function test_payment_rejection_does_not_reset_deadline(): void
    {
        // Rejection is represented by the boundary still reporting unsatisfied
        // (payment_met_at stays null) — the deadline is left untouched and the
        // booking still expires once it passes.
        $booking = $this->approvedBooking(['payment_deadline_at' => now()->subMinutes(10)]);

        // deadline unchanged (no reset on "rejection")
        $deadlineBefore = $booking->fresh()->payment_deadline_at;

        app(ExpireBookingAction::class)->execute($booking);

        $this->assertEquals($deadlineBefore, $booking->fresh()->payment_deadline_at);
        $this->assertEquals(BookingStatus::EXPIRED, $booking->fresh()->status);
    }

    public function test_completed_payment_prevents_expiry(): void
    {
        $booking = $this->approvedBooking([
            'payment_deadline_at' => now()->subMinutes(10),
            'payment_met_at' => now()->subMinutes(30), // already satisfied before deadline
        ]);

        try {
            app(ExpireBookingAction::class)->execute($booking);
            $this->fail('Expected InvalidStateTransitionException');
        } catch (InvalidStateTransitionException $e) {
            $this->assertStringContainsString('sudah terpenuhi', $e->getMessage());
        }

        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_paid_booking_skipped_by_scheduler(): void
    {
        $this->approvedBooking([
            'payment_deadline_at' => now()->subMinutes(10),
            'payment_met_at' => now()->subMinutes(30),
        ]);

        $this->artisan('bookings:expire')->assertSuccessful();

        $this->assertEquals(0, Booking::where('status', BookingStatus::EXPIRED)->count());
    }

    public function test_admin_can_manually_extend_deadline(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->approvedBooking(['payment_deadline_at' => now()->addHours(5)]);

        $oldDeadline = $booking->fresh()->payment_deadline_at;

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/extend-deadline", [
            'additional_hours' => 48,
            'reason' => 'Toleransi korporat untuk transfer antarbank.',
        ]);

        $response->assertStatus(200);
        $this->assertNotNull($booking->fresh()->payment_deadline_at);
        $this->assertTrue($booking->fresh()->payment_deadline_at->greaterThan($oldDeadline));
    }

    public function test_user_cannot_extend_deadline(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $booking = $this->approvedBooking();

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/extend-deadline", [
            'additional_hours' => 24,
            'reason' => 'Mohon perpanjangan waktu pembayaran.',
        ])->assertStatus(403);
    }

    public function test_extension_validation_requires_hours_and_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $booking = $this->approvedBooking();

        Sanctum::actingAs($admin);

        $this->postJson("/api/v1/bookings/{$booking->id}/extend-deadline", [
            'additional_hours' => 0,
            'reason' => 'ok',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['additional_hours', 'reason']);
    }
}
