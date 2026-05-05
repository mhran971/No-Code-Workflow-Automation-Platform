<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Models\Tenant;

// use Modules\Integrations\Database\Factories\IntegrationProviderFactory;

class IntegrationProvider extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'id',
        'name',
        'auth_type',
        'config_schema',
        'auth_schema',
        'is_active',
    ];

    protected $casts = [
        'config_schema' => 'array',
        'auth_schema' => 'array',
        'is_active' => 'boolean',
    ];

    public function connections(): HasMany
    {
        return $this->hasMany(IntegrationConnection::class, 'integration_provider_id');
    }

    public function connectionStatus(?Tenant $tenant = null): string
    {
        return $this->connections()
            ->where('tenant_id', $tenant ? (int) $tenant->id : null)
            ->exists() ? 'connected' : 'not_connected';
    }

    // protected static function newFactory(): IntegrationProviderFactory
    // {
    //     // return IntegrationProviderFactory::new();
    // }
}
