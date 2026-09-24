<?php

namespace Tests\Feature\Domain;

use App\Models\CustomerProfile;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAndProjectDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_one_customer_profile(): void
    {
        $user = User::factory()->create();
        $profile = CustomerProfile::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->customerProfile->is($profile));
        $this->assertTrue($profile->user->is($user));
    }

    public function test_user_has_many_project_locations(): void
    {
        $user = User::factory()->create();
        $loc1 = ProjectLocation::factory()->create(['user_id' => $user->id]);
        $loc2 = ProjectLocation::factory()->create(['user_id' => $user->id]);

        $this->assertCount(2, $user->projectLocations);
        $this->assertTrue($user->projectLocations->contains($loc1));
        $this->assertTrue($user->projectLocations->contains($loc2));
    }

    public function test_customer_profile_user_id_is_unique(): void
    {
        $user = User::factory()->create();
        CustomerProfile::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        CustomerProfile::factory()->create(['user_id' => $user->id]);
    }

    public function test_customer_profile_cascade_deletes_when_user_force_deleted(): void
    {
        $user = User::factory()->create();
        $profile = CustomerProfile::factory()->create(['user_id' => $user->id]);

        $this->assertDatabaseHas('customer_profiles', ['id' => $profile->id]);

        $user->forceDelete();

        $this->assertDatabaseMissing('customer_profiles', ['id' => $profile->id]);
    }

    public function test_project_location_restricts_user_deletion(): void
    {
        $user = User::factory()->create();
        ProjectLocation::factory()->create(['user_id' => $user->id]);

        $this->expectException(QueryException::class);
        $user->forceDelete();
    }

    public function test_project_location_supports_soft_deletes(): void
    {
        $location = ProjectLocation::factory()->create();

        $location->delete();

        $this->assertSoftDeleted('project_locations', ['id' => $location->id]);
        $this->assertNull(ProjectLocation::find($location->id));
        $this->assertNotNull(ProjectLocation::withTrashed()->find($location->id));
    }

    public function test_customer_profile_identity_number_is_unique(): void
    {
        CustomerProfile::factory()->create(['identity_number' => '1234567890']);

        $this->expectException(QueryException::class);
        CustomerProfile::factory()->create(['identity_number' => '1234567890']);
    }

    public function test_project_location_coordinates_are_nullable(): void
    {
        $location = ProjectLocation::factory()->create([
            'latitude' => null,
            'longitude' => null,
        ]);

        $this->assertDatabaseHas('project_locations', [
            'id' => $location->id,
            'latitude' => null,
            'longitude' => null,
        ]);
    }
}
