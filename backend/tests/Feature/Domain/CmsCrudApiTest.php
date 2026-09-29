<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CmsCrudApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_public_reads_default_cms_and_admin_updates_are_reflected(): void
    {
        // empty by default
        $this->getJson('/api/v1/cms/public')->assertOk()->assertJson(['success' => true]);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/cms/hero_title', ['value' => 'Sewa Alat Berat Profesional'])->assertOk();
        $this->putJson('/api/v1/admin/cms/brand_name', ['value' => 'PT RAFA Rental Nusantara'])->assertOk();

        // public landing reads the change (no auth)
        $res = $this->getJson('/api/v1/cms/public');
        $res->assertOk();
        $this->assertEquals('Sewa Alat Berat Profesional', $res->json('data.hero_title'));
        $this->assertEquals('PT RAFA Rental Nusantara', $res->json('data.brand_name'));
    }

    public function test_only_admin_can_mutate_cms(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $owner = User::factory()->owner()->create();

        // guest
        $this->putJson('/api/v1/admin/cms/hero_title', ['value' => 'x'])->assertUnauthorized();

        // USER
        Sanctum::actingAs($user);
        $this->putJson('/api/v1/admin/cms/hero_title', ['value' => 'x'])->assertForbidden();

        // OWNER read-only
        Sanctum::actingAs($owner);
        $this->putJson('/api/v1/admin/cms/hero_title', ['value' => 'x'])->assertForbidden();
        // owner may still read public content
        $this->getJson('/api/v1/cms/public')->assertOk();
    }

    public function test_media_upload_validation_and_public_url(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        // valid logo
        $up = $this->postJson('/api/v1/admin/cms/brand_logo/media', [
            'media' => UploadedFile::fake()->image('logo.png', 240, 120),
        ]);
        $up->assertOk();
        $this->assertStringContainsString('/storage/', $up->json('data.url'));

        // public shows the resolved URL
        $public = $this->getJson('/api/v1/cms/public');
        $public->assertOk();
        $this->assertStringContainsString('/storage/', $public->json('data.brand_logo'));

        // invalid mime rejected
        $this->postJson('/api/v1/admin/cms/brand_logo/media', [
            'media' => UploadedFile::fake()->create('script.php', 10, 'application/x-php'),
        ])->assertStatus(422);

        // USER upload forbidden
        $user = User::factory()->create(['role' => UserRole::USER]);
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/admin/cms/brand_logo/media', [
            'media' => UploadedFile::fake()->image('hack.png'),
        ])->assertForbidden();
    }

    public function test_replace_media_and_disable_hides_from_public(): void
    {
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/cms/hero_subtitle', ['value' => 'v1'])->assertOk();
        $this->putJson('/api/v1/admin/cms/hero_subtitle', ['value' => 'v2 updated'])->assertOk();
        $this->assertEquals('v2 updated', $this->getJson('/api/v1/cms/public')->json('data.hero_subtitle'));

        $this->deleteJson('/api/v1/admin/cms/hero_subtitle')->assertOk();
        $this->assertNull($this->getJson('/api/v1/cms/public')->json('data.hero_subtitle'));

        // replace image
        $this->postJson('/api/v1/admin/cms/hero_image/media', [
            'media' => UploadedFile::fake()->image('hero1.jpg', 1200, 600),
        ])->assertOk();
        $this->postJson('/api/v1/admin/cms/hero_image/media', [
            'media' => UploadedFile::fake()->image('hero2.jpg', 1200, 600),
        ])->assertOk();
        $this->assertNotEmpty($this->getJson('/api/v1/cms/public')->json('data.hero_image'));
    }
}
