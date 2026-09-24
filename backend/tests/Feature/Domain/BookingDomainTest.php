<?php

namespace Tests\Feature\Domain;

use App\Models\Booking;
use App\Models\BookingDetail;
use App\Models\BookingUnitAssignment;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_has_items_and_location(): void
    {
        $user = User::factory()->create();
        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);

        $cart = Cart::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $location->id,
        ]);

        $item = CartItem::factory()->create(['cart_id' => $cart->id]);

        $this->assertTrue($cart->user->is($user));
        $this->assertTrue($cart->projectLocation->is($location));
        $this->assertTrue($cart->items->contains($item));
    }

    public function test_booking_has_one_project_location(): void
    {
        $location = ProjectLocation::factory()->create();
        $booking = Booking::factory()->create(['project_location_id' => $location->id]);

        $this->assertTrue($booking->projectLocation->is($location));
    }

    public function test_booking_has_many_details(): void
    {
        $booking = Booking::factory()->create();
        $d1 = BookingDetail::factory()->create(['booking_id' => $booking->id]);
        $d2 = BookingDetail::factory()->create(['booking_id' => $booking->id]);

        $this->assertCount(2, $booking->details);
    }

    public function test_booking_detail_has_many_unit_assignments(): void
    {
        $detail = BookingDetail::factory()->create();
        $a1 = BookingUnitAssignment::factory()->create(['booking_detail_id' => $detail->id]);
        $a2 = BookingUnitAssignment::factory()->replaced()->create(['booking_detail_id' => $detail->id]);

        $this->assertCount(2, $detail->unitAssignments);
        $this->assertCount(1, $detail->currentUnitAssignments); // is_current = true
        $this->assertTrue($detail->currentUnitAssignments->first()->is($a1));
    }

    public function test_booking_code_is_unique(): void
    {
        Booking::factory()->create(['booking_code' => 'RFA-BKG-001']);

        $this->expectException(QueryException::class);
        Booking::factory()->create(['booking_code' => 'RFA-BKG-001']);
    }

    public function test_booking_restricts_location_deletion(): void
    {
        $location = ProjectLocation::factory()->create();
        Booking::factory()->create(['project_location_id' => $location->id]);

        $this->expectException(QueryException::class);
        $location->forceDelete();
    }

    public function test_booking_soft_deletes(): void
    {
        $booking = Booking::factory()->create();
        $booking->delete();

        $this->assertSoftDeleted('bookings', ['id' => $booking->id]);
        $this->assertNotNull(Booking::withTrashed()->find($booking->id));
    }

    public function test_cart_user_id_is_unique(): void
    {
        $user = User::factory()->create();
        Cart::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        Cart::factory()->create(['user_id' => $user->id]);
    }
}
