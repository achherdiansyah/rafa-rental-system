<?php

namespace Tests\Feature\Domain;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PlocStatusRegression: default is_active on CREATE must be true in DB AND in
 * the API response (previously the response returned false because the
 * freshly created model never reloaded the DB-provided default).
 */
class PlocStatusRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_response_status_matches_database_defaults(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Aktif Check',
            'email' => 'aktifcheck@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'phone_number' => '081155556666',
        ])->assertCreated();
        $login = $this->postJson('/api/v1/auth/login', ['email' => 'aktifcheck@example.com', 'password' => 'password123']);
        $login->assertOk();
        $token = $login->json('data.token');

        $create = $this->withToken($token)->postJson('/api/v1/project-locations', [
            'project_name' => 'Gudang Status',
            'pic_name' => 'Check',
            'pic_phone' => '081155556666',
            'city' => 'Bandung',
            'address' => 'Jl. Status',
        ]);
        $create->assertCreated();

        // DB row must be active (migration default true)
        $this->assertDatabaseHas('project_locations', [
            'project_name' => 'Gudang Status',
            'is_active' => true,
        ]);

        // Response must NOT claim Nonaktif: DB state and response must match
        $this->assertTrue($create->json('data.is_active'), 'create response is_active must be true');

        // List & detail keep the record with consistent status (also after a
        // "reload" — a separate authenticated request).
        $list = $this->withToken($token)->getJson('/api/v1/project-locations');
        $list->assertOk();
        $row = collect($list->json('data'))->firstWhere('id', $create->json('data.id'));
        $this->assertNotNull($row, 'newly created location must appear in list');
        $this->assertTrue($row['is_active']);

        $detail = $this->withToken($token)->getJson('/api/v1/project-locations/'.$create->json('data.id'));
        $detail->assertOk()->assertJsonPath('data.is_active', true);

        // search finds it
        $search = $this->withToken($token)->getJson('/api/v1/project-locations?search=Gudang');
        $search->assertOk();
        $this->assertContains($create->json('data.id'), collect($search->json('data'))->pluck('id')->all());
    }
}
