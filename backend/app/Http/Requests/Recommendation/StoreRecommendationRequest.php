<?php

namespace App\Http\Requests\Recommendation;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRecommendationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Only authenticated users can request a recommendation
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
            'project_type' => ['required', 'string', 'max:100'],
            'terrain_condition' => ['required', 'string', 'max:100'],
            'work_volume' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'depth_requirement' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'reach_requirement' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            'load_capacity' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
            'target_productivity' => ['nullable', 'string', 'max:100'],
            'location_access' => ['nullable', 'string', 'max:100'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'budget_range' => ['nullable', 'string', 'max:50'],
            'additional_params' => ['nullable', 'array'],
        ];
    }
}
