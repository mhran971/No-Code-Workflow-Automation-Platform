<?php

namespace Modules\Integrations\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Integrations\Database\Factories\IntegrationProviderFactory;

class IntegrationProvider extends Model
{
    use HasFactory;

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

    // protected static function newFactory(): IntegrationProviderFactory
    // {
    //     // return IntegrationProviderFactory::new();
    // }
}
