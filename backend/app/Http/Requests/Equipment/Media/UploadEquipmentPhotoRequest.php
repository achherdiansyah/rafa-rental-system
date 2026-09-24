<?php

namespace App\Http\Requests\Equipment\Media;

use App\Models\User;
use App\Support\FileSecurity;
use Illuminate\Foundation\Http\FormRequest;

class UploadEquipmentPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment');
    }

    public function rules(): array
    {
        return [
            'photo' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.FileSecurity::MAX_FILE_SIZE_KB,
            ],
        ];
    }
}
