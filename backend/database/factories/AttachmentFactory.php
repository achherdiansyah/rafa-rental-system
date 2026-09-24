<?php

namespace Database\Factories;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'attachable_type' => User::class,
            'attachable_id' => User::factory(),
            'document_type' => 'KTP',
            'file_path' => 'identity/'.now()->format('Y/m').'/'.fake()->md5().'.jpg',
            'file_name' => 'scan_ktp_2026.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 102400,
            'uploaded_by' => User::factory(),
        ];
    }
}
