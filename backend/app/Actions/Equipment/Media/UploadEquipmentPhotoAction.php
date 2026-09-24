<?php

namespace App\Actions\Equipment\Media;

use App\Models\Attachment;
use App\Models\EquipmentModel;
use App\Models\User;
use App\Support\FileSecurity;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class UploadEquipmentPhotoAction
{
    /**
     * Upload an equipment photo and associate it as a polymorphic attachment.
     */
    public function execute(EquipmentModel $model, UploadedFile $file, User $uploader): Attachment
    {
        return DB::transaction(function () use ($model, $file, $uploader) {
            $extension = strtolower($file->getClientOriginalExtension());
            $securePath = FileSecurity::generateSecurePath('equipment', $extension);

            // Store file securely in the default public/local disk
            $file->storeAs('', $securePath, 'public');

            /** @var Attachment $attachment */
            $attachment = $model->attachments()->create([
                'document_type' => 'EQUIPMENT_PHOTO',
                'file_path' => $securePath,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $uploader->id,
            ]);

            return $attachment;
        });
    }
}
