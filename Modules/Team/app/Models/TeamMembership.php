<?php

namespace Modules\Team\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\User;

class TeamMembership extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'user_id',
        'team_id',
        'status',
        'status_before_disable',
    ];

    /**
     * Get the user associated with this membership.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team associated with this membership.
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
