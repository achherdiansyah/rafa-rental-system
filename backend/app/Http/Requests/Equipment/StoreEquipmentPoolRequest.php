<?php

namespace App\Http\Requests\Equipment;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class StoreEquipmentPoolRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'address' => ['required', 'string', 'max:2000'],
            'city' => ['nullable', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'latitude.required' => 'Latitude wajib diisi.',
            'latitude.numeric' => 'Latitude harus berupa angka.',
            'latitude.between' => 'Latitude harus bernilai antara -90 dan 90.',
            'longitude.required' => 'Longitude wajib diisi.',
            'longitude.numeric' => 'Longitude harus berupa angka.',
            'longitude.between' => 'Longitude harus bernilai antara -180 dan 180.',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ((float) $this->input('latitude') === 0.0 && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', 'Koordinat 0,0 tidak dapat dipakai sebagai lokasi pool. Isi koordinat lokasi sebenarnya.');
                $validator->errors()->add('longitude', 'Koordinat 0,0 tidak dapat dipakai sebagai lokasi pool. Isi koordinat lokasi sebenarnya.');
            }
        });
    }
}