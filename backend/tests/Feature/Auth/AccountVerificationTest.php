<?php

namespace Tests\Feature\Auth;

use App\Actions\Booking\CreateBookingFromCartAction;
use App\Actions\Profile\VerifyCustomerProfileAction;
use App\Enums\UserRole;
use App\Exceptions\BusinessRuleException;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CustomerProfile;
use App\Models\EquipmentModel;
use App\Models\EquipmentPrice;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_unverified_user_checkout_is_blocked(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::USER,
            'name' => 'User Belum Verifikasi',
        ]);
        // Profile UNVERIFIED
        CustomerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Mandiri',
            'verification_status' => 'UNVERIFIED',
        ]);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 200000,
        ]);
        \App\Models\EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => \App\Enums\EquipmentStatus::AVAILABLE,
        ]);

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        // Attempt checkout as UNVERIFIED -> MUST be blocked
        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Akun Anda belum terverifikasi');

        app(CreateBookingFromCartAction::class)->execute($user, $cart);
    }

    public function test_verified_user_checkout_is_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create([
            'role' => UserRole::USER,
            'name' => 'User Terverifikasi',
        ]);
        $profile = CustomerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Mandiri Sukses',
            'verification_status' => 'UNVERIFIED',
        ]);

        $location = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $model = EquipmentModel::factory()->create(['is_active' => true]);
        EquipmentPrice::factory()->create([
            'equipment_model_id' => $model->id,
            'is_all_in' => true,
            'base_rate' => 200000,
        ]);
        \App\Models\EquipmentUnit::factory()->create([
            'equipment_model_id' => $model->id,
            'status' => \App\Enums\EquipmentStatus::AVAILABLE,
        ]);

        $cart = Cart::create(['user_id' => $user->id, 'project_location_id' => $location->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'equipment_model_id' => $model->id,
            'quantity' => 1,
            'is_all_in' => true,
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(5)->toDateString(),
        ]);

        // Admin verifies the account
        Sanctum::actingAs($admin);
        $res = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $user->id,
            'verification_status' => 'VERIFIED',
        ]);
        $res->assertOk();
        $this->assertEquals('VERIFIED', $profile->fresh()->verification_status);

        // Attempt checkout as VERIFIED -> MUST succeed
        $booking = app(CreateBookingFromCartAction::class)->execute($user->fresh(), $cart);
        $this->assertNotNull($booking);
        $this->assertEquals($user->id, $booking->user_id);
    }

    public function test_regular_user_cannot_verify_accounts_authorization_security(): void
    {
        $userA = User::factory()->create(['role' => UserRole::USER]);
        $userB = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($userA);
        // Regular user cannot access verify endpoint
        $res = $this->postJson('/api/v1/profile/verify', [
            'user_id' => $userB->id,
            'verification_status' => 'VERIFIED',
        ]);

        $res->assertStatus(403);
    }

    public function test_admin_can_view_user_directory_with_verification_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['role' => UserRole::USER, 'phone_number' => '081299887766']);
        CustomerProfile::create([
            'user_id' => $user->id,
            'company_name' => 'PT Test Profile',
            'identity_type' => 'KTP',
            'identity_number' => '3201998877660001',
            'verification_status' => 'UNVERIFIED',
        ]);

        Sanctum::actingAs($admin);
        $res = $this->getJson('/api/v1/admin/users?role=USER');

        $res->assertOk()
            ->assertJsonFragment(['name' => $user->name])
            ->assertJsonFragment(['phone_number' => '081299887766'])
            ->assertJsonFragment(['company_name' => 'PT Test Profile'])
            ->assertJsonFragment(['verification_status' => 'UNVERIFIED']);
    }
}
