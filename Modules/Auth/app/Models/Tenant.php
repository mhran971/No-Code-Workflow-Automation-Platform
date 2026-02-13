<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Enums\BusinessType;

class Tenant extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'business_name',
        'business_type',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
        ];
    }

    /**
     * Get the users for the tenant.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
