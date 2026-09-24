<?php

namespace Tests\Feature\Equipment\Media;

use App\Enums\UserRole;
use App\Models\Attachment;
use App\Models\EquipmentModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EquipmentMediaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_admin_can_upload_valid_equipment_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->image('excavator.jpg', 800, 600)->size(1024); // 1MB

        $response = $this->postJson("/api/v1/equipment/models/{$model->id}/photos", [
            'photo' => $file,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'document_type',
                    'file_name',
                    'mime_type',
                    'url',
                ],
            ]);

        $attachmentId = $response->json('data.id');

        $this->assertDatabaseHas('attachments', [
            'id' => $attachmentId,
            'attachable_type' => EquipmentModel::class,
            'attachable_id' => $model->id,
            'document_type' => 'EQUIPMENT_PHOTO',
            'uploaded_by' => $admin->id,
        ]);

        /** @var Attachment $attachment */
        $attachment = Attachment::find($attachmentId);
        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_cannot_upload_invalid_mime_file_as_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($admin);

        $file = UploadedFile::fake()->create('script.pdf', 500, 'application/pdf');

        $response = $this->postJson("/api/v1/equipment/models/{$model->id}/photos", [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_cannot_upload_photo_exceeding_max_size(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($admin);

        // 6 MB (max allowed is 5120 KB = 5 MB)
        $file = UploadedFile::fake()->image('large.png')->size(6144);

        $response = $this->postJson("/api/v1/equipment/models/{$model->id}/photos", [
            'photo' => $file,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_regular_user_cannot_upload_equipment_photo(): void
    {
        $user = User::factory()->create(['role' => UserRole::USER]);
        $model = EquipmentModel::factory()->create();

        Sanctum::actingAs($user);

        $file = UploadedFile::fake()->image('excavator.jpg');

        $response = $this->postJson("/api/v1/equipment/models/{$model->id}/photos", [
            'photo' => $file,
        ]);

        $response->assertStatus(403);
    }

    public function test_admin_can_delete_equipment_photo(): void
    {
        $admin = User::factory()->admin()->create();
        $model = EquipmentModel::factory()->create();

        $path = 'equipment/2026/09/test-hash.jpg';
        Storage::disk('public')->put($path, 'dummy-content');

        /** @var Attachment $attachment */
        $attachment = $model->attachments()->create([
            'document_type' => 'EQUIPMENT_PHOTO',
            'file_path' => $path,
            'file_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 100,
            'uploaded_by' => $admin->id,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/api/v1/equipment/models/{$model->id}/photos/{$attachment->id}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('attachments', ['id' => $attachment->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_public_can_view_model_details_with_photos(): void
    {
        $model = EquipmentModel::factory()->create();
        $path = 'equipment/2026/09/public-view.jpg';

        $model->attachments()->create([
            'document_type' => 'EQUIPMENT_PHOTO',
            'file_path' => $path,
            'file_name' => 'excavator_front.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
            'uploaded_by' => User::factory()->create()->id,
        ]);

        $response = $this->getJson("/api/v1/equipment/models/{$model->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'model_name',
                    'attachments' => [
                        '*' => ['id', 'document_type', 'file_name', 'url'],
                    ],
                ],
            ]);

        $this->assertCount(1, $response->json('data.attachments'));
    }
}
