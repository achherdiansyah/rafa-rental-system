<?php

namespace App\Http\Requests\ProjectLocation;

use App\Http\Requests\Concerns\ValidatesRegionSelection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreProjectLocationRequest extends FormRequest
{
    use ValidatesRegionSelection;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string'],
            'city' => ['required', 'string', 'max:100'],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_phone' => ['required', 'string', 'max:30'],
            'province_code' => ['nullable', 'string', 'max:20'],
            'province_name' => ['nullable', 'string', 'max:150'],
            'regency_code' => ['nullable', 'string', 'max:20'],
            'regency_name' => ['nullable', 'string', 'max:150'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'district_name' => ['nullable', 'string', 'max:150'],
            'village_code' => ['nullable', 'string', 'max:20'],
            'village_name' => ['nullable', 'string', 'max:150'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $this->validateRegionHierarchy($validator);
        $validator->after(function ($validator) {
            if ($this->has(['latitude', 'longitude'])
                && (float) $this->input('latitude') === 0.0
                && (float) $this->input('longitude') === 0.0) {
                $validator->errors()->add('latitude', 'Koordinat 0,0 tidak dapat dipakai.');
                $validator->errors()->add('longitude', 'Koordinat 0,0 tidak dapat dipakai.');
            }
        });
    }
}




