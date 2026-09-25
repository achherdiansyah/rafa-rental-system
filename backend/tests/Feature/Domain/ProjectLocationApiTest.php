<?php

namespace Tests\Feature\Domain;

use App\Enums\BookingStatus;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProjectLocationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_project_location(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $payload = [
            'project_name' => 'Pembangunan Flyover Cisauk',
            'address' => 'Jl. Raya Cisauk Lapan No. 45',
            'city' => 'Tangerang',
            'pic_name' => 'Bambang Sutrisno',
            'pic_phone' => '081234567890',
            'latitude' => -6.32145678,
            'longitude' => 106.65432100,
            'is_active' => true,
        ];

        $response = $this->postJson('/api/v1/project-locations', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'project_name' => 'Pembangunan Flyover Cisauk',
                    'city' => 'Tangerang',
                    'pic_name' => 'Bambang Sutrisno',
                    'user_id' => $user->id,
                ],
            ]);

        $this->assertDatabaseHas('project_locations', [
            'user_id' => $user->id,
            'project_name' => 'Pembangunan Flyover Cisauk',
            'city' => 'Tangerang',
        ]);
    }

    public function test_user_can_only_list_own_project_locations(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        $loc1 = ProjectLocation::factory()->create(['user_id' => $user1->id, 'project_name' => 'Project Milik User 1']);
        $loc2 = ProjectLocation::factory()->create(['user_id' => $user2->id, 'project_name' => 'Project Milik User 2']);

        Sanctum::actingAs($user1);

        $response = $this->getJson('/api/v1/project-locations');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals($loc1->id, $response->json('data.0.id'));
        $this->assertEquals('Project Milik User 1', $response->json('data.0.project_name'));
    }

    public function test_user_can_view_own_project_location_detail(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $loc = ProjectLocation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/project-locations/{$loc->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $loc->id,
                    'user_id' => $user->id,
                ],
            ]);
    }

    public function test_user_cannot_view_or_modify_other_users_project_location(): void
    {
        $user1 = User::factory()->create(['role' => UserRole::USER]);
        $user2 = User::factory()->create(['role' => UserRole::USER]);

        $loc = ProjectLocation::factory()->create(['user_id' => $user2->id]);

        Sanctum::actingAs($user1);

        // View detail -> 403
        $this->getJson("/api/v1/project-locations/{$loc->id}")->assertStatus(403);

        // Update -> 403
        $this->putJson("/api/v1/project-locations/{$loc->id}", [
            'project_name' => 'Hijacked Project',
        ])->assertStatus(403);

        // Delete -> 403
        $this->deleteJson("/api/v1/project-locations/{$loc->id}")->assertStatus(403);
    }

    public function test_user_can_update_own_project_location(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $loc = ProjectLocation::factory()->create([
            'user_id' => $user->id,
            'project_name' => 'Old Project Name',
        ]);

        Sanctum::actingAs($user);

        $response = $this->putJson("/api/v1/project-locations/{$loc->id}", [
            'project_name' => 'Updated Project Name',
            'city' => 'Bekasi',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'project_name' => 'Updated Project Name',
                    'city' => 'Bekasi',
                ],
            ]);

        $this->assertEquals('Updated Project Name', $loc->fresh()->project_name);
        $this->assertEquals('Bekasi', $loc->fresh()->city);
    }

    public function test_user_can_delete_own_unused_project_location(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $loc = ProjectLocation::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/project-locations/{$loc->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('project_locations', ['id' => $loc->id]);
    }

    public function test_cannot_delete_project_location_with_active_bookings(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $loc = ProjectLocation::factory()->create(['user_id' => $user->id]);

        Booking::factory()->create([
            'user_id' => $user->id,
            'project_location_id' => $loc->id,
            'status' => BookingStatus::CONFIRMED,
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson("/api/v1/project-locations/{$loc->id}");

        $response->assertStatus(409)
            ->assertJson([
                'success' => false,
                'code' => 'CONFLICT',
            ]);

        $this->assertNotSoftDeleted('project_locations', ['id' => $loc->id]);
    }

    public function test_admin_and_owner_can_view_any_project_location(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->owner()->create();
        $user = User::factory()->create(['role' => UserRole::USER]);

        $loc = ProjectLocation::factory()->create(['user_id' => $user->id]);

        // Admin
        Sanctum::actingAs($admin);
        $this->getJson("/api/v1/project-locations/{$loc->id}")->assertStatus(200);
        $resAdmin = $this->getJson("/api/v1/project-locations?user_id={$user->id}");
        $resAdmin->assertStatus(200);
        $this->assertCount(1, $resAdmin->json('data'));

        // Owner
        Sanctum::actingAs($owner);
        $this->getJson("/api/v1/project-locations/{$loc->id}")->assertStatus(200);
    }

    public function test_validation_fails_for_missing_required_fields(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/project-locations', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'project_name',
                'address',
                'city',
                'pic_name',
                'pic_phone',
            ]);
    }

    public function test_validation_fails_for_invalid_coordinates(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Test Location',
            'address' => 'Jl. Test',
            'city' => 'Jakarta',
            'pic_name' => 'Budi',
            'pic_phone' => '0812345',
            'latitude' => 120.00, // Invalid: must be between -90 and 90
            'longitude' => 200.00, // Invalid: must be between -180 and 180
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->getJson('/api/v1/project-locations')->assertStatus(401);
        $this->postJson('/api/v1/project-locations', [])->assertStatus(401);
    }
}
