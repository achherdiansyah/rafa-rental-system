<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\ProjectLocation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Pre-Phase 14 (repro): Project Location persistence + ownership isolation.
 * Raw API flow proof — POST → DB → GET list → GET detail → refresh.
 * Uses withToken() (identical to the real single Authorization header a
 * browser sends) so session-level header pollution can never skew results.
 */
class ProjectLocationPersistenceTest extends TestCase
{
    use RefreshDatabase;

    private function registerAndLogin(string $email): string
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Dono',
            'email' => $email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '081'.substr(md5($email), 0, 9),
        ])->assertCreated();

        $login = $this->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password123']);
        $login->assertOk();

        return $login->json('data.token');
    }

    public function test_post_persists_db_and_immediately_visible_on_list_and_detail(): void
    {
        $tokenA = $this->registerAndLogin('ape@example.com');

        // A. POST create (withToken = the exact Authorization header a browser sends)
        $create = $this->withToken($tokenA)->postJson('/api/v1/project-locations', [
            'project_name' => 'Proyek Persistensi',
            'address' => 'Jl. Bukti No.1',
            'city' => 'Karawang',
            'pic_name' => 'PIC A',
            'pic_phone' => '081211111111',
        ]);
        $create->assertCreated();
        $createdId = $create->json('data.id');
        $this->assertNotNull($createdId);

        // B. DB row exists with correct ownership
        $this->assertDatabaseHas('project_locations', [
            'id' => $createdId,
            'project_name' => 'Proyek Persistensi',
        ]);
        $row = ProjectLocation::find($createdId);
        $this->assertEquals($row->user_id, User::where('email', 'ape@example.com')->first()->id);
        $this->assertNull($row->deleted_at);

        // C. GET list immediately returns the record (no filter/ownership skew)
        $list = $this->withToken($tokenA)->getJson('/api/v1/project-locations?page=1&per_page=9');
        $list->assertOk();
        $ids = collect($list->json('data'))->pluck('id')->all();
        $this->assertContains($createdId, $ids);

        // D. GET detail reachable by the owner
        $this->withToken($tokenA)->getJson("/api/v1/project-locations/{$createdId}")
            ->assertOk()
            ->assertJsonPath('data.project_name', 'Proyek Persistensi');

        // E. Refresh-equivalent: a brand-new login (new browser session) still sees it
        $relogin = $this->postJson('/api/v1/auth/login', ['email' => 'ape@example.com', 'password' => 'password123']);
        $relogin->assertOk();
        $refresh = $this->withToken($relogin->json('data.token'))->getJson('/api/v1/project-locations');
        $refresh->assertOk();
        $this->assertContains($createdId, collect($refresh->json('data'))->pluck('id')->all());
    }

    public function test_user_b_cannot_read_update_or_delete_user_a_location(): void
    {
        $userA = User::factory()->create(['role' => UserRole::USER]);
        $userB = User::factory()->create(['role' => UserRole::USER]);

        Sanctum::actingAs($userA);
        $createdId = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Rahasia A',
            'address' => 'Jl. A',
            'city' => 'Jakarta',
            'pic_name' => 'A',
            'pic_phone' => '081200',
        ])->assertCreated()->json('data.id');

        Sanctum::actingAs($userB);
        $listB = $this->getJson('/api/v1/project-locations');
        $listB->assertOk();
        $this->assertNotContains($createdId, collect($listB->json('data'))->pluck('id')->all());

        $this->getJson("/api/v1/project-locations/{$createdId}")->assertForbidden();
        $this->putJson("/api/v1/project-locations/{$createdId}", ['project_name' => 'Diretas'])->assertForbidden();
        $this->deleteJson("/api/v1/project-locations/{$createdId}")->assertForbidden();
    }

    public function test_crud_update_and_safe_delete_flow(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);

        $id = $this->postJson('/api/v1/project-locations', [
            'project_name' => 'Sebelum',
            'address' => 'Jl. X',
            'city' => 'Depok',
            'pic_name' => 'Dono',
            'pic_phone' => '0813',
        ])->assertCreated()->json('data.id');

        // UPDATE → persist → re-read
        $this->putJson("/api/v1/project-locations/{$id}", [
            'project_name' => 'Sesudah',
            'city' => 'Bogor',
        ])->assertOk();
        $this->assertDatabaseHas('project_locations', ['id' => $id, 'project_name' => 'Sesudah', 'city' => 'Bogor']);
        $this->getJson("/api/v1/project-locations/{$id}")
            ->assertOk()
            ->assertJsonPath('data.project_name', 'Sesudah');

        // DELETE (no active booking → allowed; soft delete protects history FKs)
        $this->deleteJson("/api/v1/project-locations/{$id}")->assertOk();
        $this->assertSoftDeleted('project_locations', ['id' => $id]);
        $this->assertNotContains($id, collect($this->getJson('/api/v1/project-locations')->json('data'))->pluck('id')->all());
    }
}
