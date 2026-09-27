<?php

namespace App\Http\Requests\Refund;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class FailRefundRequest extends FormRequest
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
            'failure_reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }
}
