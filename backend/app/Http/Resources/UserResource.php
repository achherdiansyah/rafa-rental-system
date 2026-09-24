<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role?->value ?? (string) $this->role,
            'phone_number' => $this->phone_number,
            'is_active' => (bool) $this->is_active,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'customer_profile' => $this->whenLoaded('customerProfile', function () {
                return [
                    'company_name' => $this->customerProfile->company_name,
                    'identity_type' => $this->customerProfile->identity_type,
                    'identity_number' => $this->customerProfile->identity_number,
                    'address' => $this->customerProfile->address,
                    'verification_status' => $this->customerProfile->verification_status,
                ];
            }),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
