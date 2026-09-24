<?php

namespace App\Actions\Equipment\Media;

use App\Models\Attachment;
use App\Models\EquipmentModel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteEquipmentPhotoAction
{
    /**
     * Delete an equipment attachment and remove the physical file.
     */
    public function execute(EquipmentModel $model, Attachment $attachment): void
    {
        DB::transaction(function () use ($model, $attachment) {
            // Ensure the attachment belongs to this model
            if ($attachment->attachable_type !== EquipmentModel::class || $attachment->attachable_id !== $model->id) {
                return;
            }

            if (Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            $attachment->delete();
        });
    }
}
