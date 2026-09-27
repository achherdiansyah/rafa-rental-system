<?php

namespace App\Http\Requests\Refund;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ApproveRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = $this->user();

        return $user !== null && $user->isOwner();
    }

    public function rules(): array
    {
        return [
            'approval_reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
