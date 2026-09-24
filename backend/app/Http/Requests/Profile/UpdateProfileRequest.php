<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var User $user */
        $user = $this->user();
        $customerProfile = $user->customerProfile;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'phone_number' => [
                'sometimes',
                'required',
                'string',
                'max:30',
                Rule::unique('users', 'phone_number')->ignore($user->id),
            ],
            'company_name' => ['nullable', 'string', 'max:255'],
            'identity_type' => ['sometimes', 'nullable', 'string', 'in:KTP,NPWP,PASSPORT'],
            'identity_number' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('customer_profiles', 'identity_number')->ignore($customerProfile?->id),
            ],
            'address' => ['nullable', 'string'],
        ];
    }
}
