<?php

namespace App\Http\Requests\Invoice;

use App\Enums\InvoiceType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateInvoiceRequest extends FormRequest
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
            'booking_id' => ['required', 'integer', 'exists:bookings,id'],
            'invoice_type' => ['required', 'string', Rule::in([
                InvoiceType::DAILY_WORK->value,
                InvoiceType::MOB_DEMOB->value,
            ])],
        ];
    }
}
