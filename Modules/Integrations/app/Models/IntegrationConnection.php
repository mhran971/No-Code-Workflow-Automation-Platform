<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Integrations\Database\Factories\IntegrationConnectionFactory;

class IntegrationConnection extends Model
{
    use HasFactory;

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

    // protected static function newFactory(): IntegrationConnectionFactory
    // {
    //     // return IntegrationConnectionFactory::new();
    // }
}
