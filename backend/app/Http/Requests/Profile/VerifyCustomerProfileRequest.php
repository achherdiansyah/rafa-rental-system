<?php

namespace App\Http\Requests\Profile;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VerifyCustomerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->can('manage-equipment'); // Admin or Owner gate
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'verification_status' => ['required', 'string', 'in:VERIFIED,REJECTED,UNVERIFIED'],
        ];
    }
}
