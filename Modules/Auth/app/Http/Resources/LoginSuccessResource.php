<?php

namespace Modules\Auth\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\Models\User;

/**
 * @mixin User
 */
class LoginSuccessResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => 'Login successful.',
            'redirect_url' => url('/dashboard'),
            'token' => $this->additional['token'] ?? null,
            'expires_in_minutes' => (int) config('jwt.ttl', 1440),
            'user' => [
                'id' => $this->id,
                'email' => $this->email,
                'first_name' => $this->first_name,
                'last_name' => $this->last_name,
                'tenant' => $this->whenLoaded('tenant', fn () => [
                    'id' => $this->tenant->id,
                    'business_name' => $this->tenant->business_name,
                ]),
                'role' => $this->role,
            ],
        ];
    }
}
