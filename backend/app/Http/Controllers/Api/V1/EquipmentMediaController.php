<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Equipment\Media\DeleteEquipmentPhotoAction;
use App\Actions\Equipment\Media\UploadEquipmentPhotoAction;
use App\Http\Controllers\Api\ApiController;
use App\Http\Requests\Equipment\Media\UploadEquipmentPhotoRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\EquipmentModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EquipmentMediaController extends ApiController
{
    /**
     * Upload photo attachment for equipment model.
     */
    public function uploadPhoto(UploadEquipmentPhotoRequest $request, EquipmentModel $model, UploadEquipmentPhotoAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $attachment = $action->execute($model, $request->file('photo'), $user);

        return $this->created(new AttachmentResource($attachment), 'Foto alat berat berhasil diunggah.');
    }

    /**
     * Delete photo attachment from equipment model.
     */
    public function deletePhoto(Request $request, EquipmentModel $model, Attachment $attachment, DeleteEquipmentPhotoAction $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->can('manage-equipment')) {
            return $this->forbidden('Anda tidak memiliki izin untuk menghapus foto armada.');
        }

        $action->execute($model, $attachment);

        return $this->success(null, 'Foto armada berhasil dihapus.');
    }
}
