<?php

namespace Tests\Feature\Booking;

use App\Actions\Booking\ApproveBookingAction;
use App\Actions\Booking\AssignBookingUnitsAction;
use App\Actions\Booking\CreateBookingFromCartAction;
use App\Actions\Booking\ExpireBookingAction;
use App\Enums\AssignmentStatus;
use App\Enums\BookingStatus;
use App\Enums\EquipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Booking;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\EquipmentUnit;
use App\Models\Invoice;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AvailabilityConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        $user = User::factory()->create();
        \App\Models\CustomerProfile::factory()->create([
            'user_id' => $user->id,
            'verification_status' => 'VERIFIED',
        ]);
        return $user;
    }

    public function test_booking_concurrency_race_condition(): void
    {
        // 1. Setup Data: 1 Model, 1 Unit Fisik
        $admin = User::factory()->admin()->create();
        
        $userA = $this->verifiedUser();
        $userB = $this->verifiedUser();

        $locationA = ProjectLocation::factory()->create(['user_id' => $userA->id]);
        $locationB = ProjectLocation::factory()->create(['user_id' => $userB->id]);

        $model = EquipmentModel::factory()->create(['is_active' => true, 'model_name' => 'PC200-8']);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 200000,
            'effective_date' => now()->subMonth(),
        ]);
        
        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $startDate = now()->addDays(2);
        $endDate = now()->addDays(6);

        // 2. User A adds to Cart
        $cartA = Cart::create(['user_id' => $userA->id, 'project_location_id' => $locationA->id]);
        CartItem::create([
            'cart_id' => $cartA->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        // 3. User B adds to Cart and checkouts first
        $cartB = Cart::create(['user_id' => $userB->id, 'project_location_id' => $locationB->id]);
        CartItem::create([
            'cart_id' => $cartB->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        $bookingB = app(CreateBookingFromCartAction::class)->execute($userB, $cartB);

        // At this point User B has a DRAFT booking, it does NOT consume capacity yet.
        // If User A checkouts now, it would work. But let's say Admin approves User B first.
        
        // 4. Admin approves User B
        // Note: ApproveBookingAction in real life comes after SUBMIT (PENDING_APPROVAL).
        $bookingB->update(['status' => BookingStatus::PENDING_APPROVAL]);
        $bookingB = app(ApproveBookingAction::class)->execute($admin, $bookingB);
        
        // Admin assigns unit to B
        $bDetailId = $bookingB->details()->first()->id;
        app(AssignBookingUnitsAction::class)->execute($admin, $bookingB, [
            ['booking_detail_id' => $bDetailId, 'equipment_unit_id' => $unit->id]
        ]);

        // At this point, User B is occupying the 1 unit.
        
        // 5. User A tries to checkout (should fail)
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Ketersediaan armada untuk model ID #'.$model->id.' tidak mencukupi');
        app(CreateBookingFromCartAction::class)->execute($userA, $cartA);
    }

    public function test_expired_booking_releases_unit_making_it_available(): void
    {
        $admin = User::factory()->admin()->create();
        $userA = $this->verifiedUser();
        $userB = $this->verifiedUser();

        $locationA = ProjectLocation::factory()->create(['user_id' => $userA->id]);
        $locationB = ProjectLocation::factory()->create(['user_id' => $userB->id]);

        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 200000,
        ]);

        $unit = EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => EquipmentStatus::AVAILABLE,
        ]);

        $startDate = now()->addDays(2);
        $endDate = now()->addDays(6);

        // User B books and gets approved
        $bookingB = Booking::factory()->create([
            'user_id' => $userB->id,
            'project_location_id' => $locationB->id,
            'status' => BookingStatus::APPROVED,
            'payment_deadline_at' => now()->subHour(), // Expired
        ]);
        $detailB = \App\Models\BookingDetail::factory()->create([
            'booking_id' => $bookingB->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);
        \App\Models\BookingUnitAssignment::create([
            'booking_detail_id' => $detailB->id,
            'equipment_unit_id' => $unit->id,
            'status' => AssignmentStatus::ASSIGNED,
            'is_current' => true,
            'assigned_by' => $admin->id,
        ]);
        $unit->update(['status' => EquipmentStatus::ASSIGNED]);

        // User A tries to checkout - fails because B is holding it
        $cartA = Cart::create(['user_id' => $userA->id, 'project_location_id' => $locationA->id]);
        CartItem::create([
            'cart_id' => $cartA->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
        ]);

        $failed = false;
        try {
            app(CreateBookingFromCartAction::class)->execute($userA, $cartA);
        } catch (BusinessRuleException $e) {
            $failed = true;
        }
        $this->assertTrue($failed);

        // Now expire User B
        app(ExpireBookingAction::class)->execute($bookingB);

        $this->assertEquals(BookingStatus::EXPIRED->value, $bookingB->fresh()->status->value);
        $this->assertEquals(EquipmentStatus::AVAILABLE->value, $unit->fresh()->status->value);

        // User A tries again - succeeds
        $bookingA = app(CreateBookingFromCartAction::class)->execute($userA, $cartA);
        $this->assertEquals(BookingStatus::DRAFT->value, $bookingA->status->value);
    }
}