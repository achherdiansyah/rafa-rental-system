<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone_number' => ['required', 'string', 'max:30', 'unique:users,phone_number'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'identity_type' => ['nullable', 'string', 'in:KTP,NPWP,PASSPORT'],
            'identity_number' => ['nullable', 'string', 'max:100', 'unique:customer_profiles,identity_number'],
            'address' => ['nullable', 'string'],
        ];
    }
}
