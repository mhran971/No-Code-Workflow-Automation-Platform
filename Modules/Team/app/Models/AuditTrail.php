<?php

namespace Modules\Team\Models;

use Illuminate\Database\Eloquent\Model;

class AuditTrail extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'actor_user_id',
        'actor_name',
        'actor_email',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
