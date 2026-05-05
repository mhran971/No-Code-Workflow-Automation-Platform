<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;

// use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;

class IntegrationConnection extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'integration_provider_id',
        'tenant_id',
        'auth_config',
        'config',
    ];

    protected $casts = [
        'auth_config' => 'array',
        'config' => 'array',
    ];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(IntegrationProvider::class, 'integration_provider_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    // protected static function newFactory(): IntegrationConnectionFactory
    // {
    //     // return IntegrationConnectionFactory::new();
    // }
}
