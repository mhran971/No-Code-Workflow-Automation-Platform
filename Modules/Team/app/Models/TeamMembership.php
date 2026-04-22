<?php

namespace Modules\Team\Models;

use Illuminate\Database\Eloquent\Model;

class TeamMembership extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'status',
        'status_before_disable',
    ];
}
