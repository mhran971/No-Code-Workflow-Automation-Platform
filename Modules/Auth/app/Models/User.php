<?php

namespace Modules\Auth\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Auth\Enums\Role;
use Modules\Team\Models\Team;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'position',
        'name',
        'email',
        'password',
        'tenant_id',
        'role',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the tenant that the user belongs to.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the identifier that will be stored in the JWT subject claim.
     */
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    /**
     * Get the custom claims to be added to the JWT (includes tenant for context).
     */
    public function getJWTCustomClaims(): array
    {
        $tenant = $this->relationLoaded('tenant')
            ? $this->tenant
            : $this->tenant()->first();

        return [
            'tenant' => $tenant ? [
                'id' => $tenant->id,
                'business_name' => $tenant->business_name,
                'business_type' => $tenant->business_type?->value ?? $tenant->business_type,
            ] : null,
        ];
    }

    public function getManagedTeam()
    {
        if ($this->role !== Role::Manager) {
            return null;
        }

        return $this->hasOne(Team::class, 'manager_id', 'id')->where('tenant_id', $this->tenant_id)->first();
    }
}
