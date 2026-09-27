<?php

namespace Tests\Feature\Domain;

use App\Actions\Booking\CancelBookingAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\EquipmentModel;
use App\Models\EquipmentUnit;
use App\Models\ProjectLocation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BookingCancellationRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Log::spy();
    }

    private function booking(User $user, BookingStatus|string $status): Booking
    {
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
            'status' => $status instanceof BookingStatus ? $status->value : $status,
        ]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        $start = Carbon::parse(now()->addDays(5)->toDateString());
        BookingDetail::factory()->create([
            'booking_id' => $booking->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => $start->toDateString(),
            'end_date' => $start->addDays(4)->toDateString(),
        ]);

        return $booking->load('details');
    }

    private function assignSlot(Booking $booking, EquipmentStatus|string $unitStatus = EquipmentStatus::ASSIGNED): void
    {
        $admin = User::factory()->admin()->create();
        $modelId = $booking->details->first()->equipment_model_id;
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $modelId,
            'status' => $unitStatus instanceof EquipmentStatus ? $unitStatus->value : $unitStatus,
        ]);

        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $booking->details->first()->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
    }

    private function cancel(User $actor, Booking $booking, string $reason): TestResponse
    {
        Sanctum::actingAs($actor);

        return $this->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => $reason]);
    }

    public function test_user_can_cancel_before_payment(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);

        $response = $this->cancel($user, $booking, 'Proyek ditunda oleh pemilik gedung.');
        $response->assertStatus(200);

        $fresh = $booking->fresh();
        $this->assertEquals(BookingStatus::CANCELLED, $fresh->status);
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertEquals('Proyek ditunda oleh pemilik gedung.', $fresh->cancellation_reason);
    }

    public function test_user_cancel_releases_assigned_slots(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);
        $this->assignSlot($booking);

        $unit = BookingUnitAssignment::first()->unit;

        $this->cancel($user, $booking, 'Kebutuhan armada batal karena pindah metode.');

        $assignment = BookingUnitAssignment::first();
        $this->assertEquals(false, $assignment->fresh()->is_current);
        $this->assertEquals(AssignmentStatus::CANCELLED, $assignment->fresh()->status);
        $this->assertEquals(EquipmentStatus::AVAILABLE, $unit->fresh()->status);
    }

    public function test_user_cannot_cancel_after_payment_is_satisfied(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);
        $booking->update(['payment_met_at' => now()->subMinutes(5)]);

        $response = $this->cancel($user, $booking, 'Ingin membatalkan setelah bayar.');
        $response->assertStatus(409)
            ->assertJson(['code' => 'INVALID_STATE_TRANSITION']);
        $this->assertEquals(BookingStatus::APPROVED, $booking->fresh()->status);
    }

    public function test_admin_can_cancel_after_payment_with_refund_boundary(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);
        $booking->update(['payment_met_at' => now()->subMinutes(5)]);

        $response = $this->cancel($admin, $booking, 'Pengembalian dana via policy perusahaan.');
        $response->assertStatus(200);
        $this->assertEquals(BookingStatus::CANCELLED, $booking->fresh()->status);
    }

    public function test_cancel_after_confirmed_requires_admin(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::CONFIRMED);

        // USER blocked
        $this->cancel($user, $booking, 'Membatalkan booking terkonfirmasi.')->assertStatus(409);

        // ADMIN allowed
        $admin = User::factory()->admin()->create();
        $this->cancel($admin, $booking, 'Disetujui pembatalan oleh tim operasional.')->assertStatus(200);
    }

    public function test_cancel_after_dispatch_is_forbidden(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::DISPATCHED);

        // Even ADMIN cannot cancel a dispatched/in-operation booking
        $this->expectException(InvalidStateTransitionException::class);
        app(CancelBookingAction::class)->execute($admin, $booking, 'Mencoba batal setelah dispatch.');
    }

    public function test_other_user_cannot_cancel_someone_elses_booking(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $booking = $this->booking($owner, BookingStatus::APPROVED);

        $this->cancel($intruder, $booking, 'Mencoba membatalkan booking orang lain.')
            ->assertStatus(409);
    }

    public function test_cancellation_requires_reason(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);

        Sanctum::actingAs($user);
        $this->postJson("/api/v1/bookings/{$booking->id}/cancel", ['reason' => 'tdk'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_user_reschedule_requires_approval_and_updates_dates(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);

        // Give the model physical capacity
        EquipmentUnit::factory()->count(2)->create([
            'equipment_model_id' => $booking->details->first()->equipment_model_id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($user);

        $newStart = now()->addDays(20)->toDateString();
        $newEnd = now()->addDays(25)->toDateString();

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => $newStart,
            'new_end_date' => $newEnd,
            'reason' => 'Penundaan pekerjaan kontraktor utama.',
        ]);

        $response->assertStatus(200);
        $fresh = $booking->fresh();

        // Moves back to PENDING_APPROVAL for Admin confirmation
        $this->assertEquals(BookingStatus::PENDING_APPROVAL, $fresh->status);
        $this->assertNotNull($fresh->reschedule_requested_at);
        $this->assertEquals(now()->addDays(20)->format('Y-m-d'), $fresh->details->first()->start_date->toDateString());
        $this->assertNotEmpty($fresh->reschedule_history);
        $this->assertEquals($newStart, $fresh->reschedule_history[0]['new_start']);
    }

    public function test_reschedule_rejects_unavailable_window(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);

        // Occupy all units of the model for the new window
        $modelId = $booking->details->first()->equipment_model_id;
        EquipmentUnit::factory()->create(['equipment_model_id' => $modelId, 'status' => EquipmentStatus::AVAILABLE]);
        EquipmentUnit::factory()->create(['equipment_model_id' => $modelId, 'status' => EquipmentStatus::AVAILABLE]);

        $blocker = User::factory()->create();
        $blockerBooking = Booking::factory()->create(['user_id' => $blocker->id, 'status' => BookingStatus::CONFIRMED]);
        $blockerDetail = BookingDetail::factory()->create([
            'booking_id' => $blockerBooking->id,
            'equipment_model_id' => $modelId,
            'quantity' => 2,
            'start_date' => now()->addDays(30)->toDateString(),
            'end_date' => now()->addDays(35)->toDateString(),
        ]);
        foreach (EquipmentUnit::where('equipment_model_id', $modelId)->get() as $unit) {
            BookingUnitAssignment::factory()->create([
                'booking_detail_id' => $blockerDetail->id,
                'equipment_unit_id' => $unit->id,
                'is_current' => true,
                'assigned_by' => User::factory()->admin()->create()->id,
            ]);
            $unit->update(['status' => EquipmentStatus::ASSIGNED]);
        }

        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => now()->addDays(30)->toDateString(),
            'new_end_date' => now()->addDays(35)->toDateString(),
            'reason' => 'Ingin mundur ke tanggal penuh.',
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
        // Dates untouched
        $this->assertNotEquals(now()->addDays(30)->format('Y-m-d'), $booking->fresh()->details->first()->start_date->toDateString());
    }

    public function test_reschedule_buffer_conflict_is_blocked(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::APPROVED);

        $modelId = $booking->details->first()->equipment_model_id;
        $unit = EquipmentUnit::factory()->create(['equipment_model_id' => $modelId, 'status' => EquipmentStatus::AVAILABLE]);

        // Existing committed slot ends within the +5 day buffer of the new window
        $blocker = User::factory()->create();
        $blockerBooking = Booking::factory()->create(['user_id' => $blocker->id, 'status' => BookingStatus::CONFIRMED]);
        $blockerDetail = BookingDetail::factory()->create([
            'booking_id' => $blockerBooking->id,
            'equipment_model_id' => $modelId,
            'quantity' => 1,
            'start_date' => now()->addDays(12)->toDateString(),
            'end_date' => now()->addDays(15)->toDateString(),
        ]);
        BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $blockerDetail->id,
            'equipment_unit_id' => $unit->id,
            'is_current' => true,
            'assigned_by' => User::factory()->admin()->create()->id,
        ]);
        $unit->update(['status' => EquipmentStatus::ASSIGNED]);

        Sanctum::actingAs($user);

        // New window starts inside the committed slot's buffer (ends +5 days = day 21)
        $response = $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => now()->addDays(18)->toDateString(),
            'new_end_date' => now()->addDays(22)->toDateString(),
            'reason' => 'Reschedule mendekati slot lain.',
        ]);

        $response->assertStatus(409)
            ->assertJson(['code' => 'BUSINESS_RULE_VIOLATION']);
    }

    public function test_reschedule_keeps_payment_metadata_untouched(): void
    {
        $user = User::factory()->create();
        $booking = $this->booking($user, BookingStatus::PAYMENT_PENDING);
        $booking->update(['payment_met_at' => now()->subHour()]);

        EquipmentUnit::factory()->count(2)->create([
            'equipment_model_id' => $booking->details->first()->equipment_model_id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/bookings/{$booking->id}/reschedule", [
            'new_start_date' => now()->addDays(20)->toDateString(),
            'new_end_date' => now()->addDays(25)->toDateString(),
            'reason' => 'Menyesuaikan jadwal proyek baru.',
        ])->assertStatus(200);

        // payment history/metadata unchanged
        $fresh = $booking->fresh();
        $this->assertNotNull($fresh->payment_met_at);
    }

    public function test_unit_replacement_still_guarded_and_audited(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        $booking = $this->booking($user, BookingStatus::APPROVED);
        $detail = $booking->details->first();
        $detail->update(['start_date' => now()->addDays(5)->toDateString(), 'end_date' => now()->addDays(9)->toDateString()]);

        $modelId = $detail->equipment_model_id;
        $unitA = EquipmentUnit::factory()->create(['equipment_model_id' => $modelId, 'status' => EquipmentStatus::AVAILABLE]);
        $unitB = EquipmentUnit::factory()->create(['equipment_model_id' => $modelId, 'status' => EquipmentStatus::AVAILABLE]);

        $assignment = BookingUnitAssignment::factory()->create([
            'booking_detail_id' => $detail->id,
            'equipment_unit_id' => $unitA->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $unitA->update(['status' => EquipmentStatus::ASSIGNED]);

        Sanctum::actingAs($admin);

        $response = $this->postJson("/api/v1/bookings/{$booking->id}/assignments/{$assignment->id}/replace", [
            'new_equipment_unit_id' => $unitB->id,
            'reason' => 'Unit A rusak sebelum pengiriman.',
        ]);

        $response->assertStatus(200);

        // History preserved
        $this->assertEquals(false, $assignment->fresh()->is_current);
        $this->assertEquals(AssignmentStatus::REPLACED, $assignment->fresh()->status);

        $new = BookingUnitAssignment::where('booking_detail_id', $detail->id)->where('is_current', true)->first();
        $this->assertEquals($unitB->id, $new->equipment_unit_id);
        $this->assertEquals(EquipmentStatus::MAINTENANCE, $unitA->fresh()->status);
        $this->assertEquals(EquipmentStatus::ASSIGNED, $unitB->fresh()->status);
    }
}
