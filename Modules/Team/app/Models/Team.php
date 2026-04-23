<?php

namespace Modules\Team\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

class Team extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'manager_id',
    ];

    /**
     * Get the tenant for the team.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the current manager of the team.
     */
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get team memberships for this team.
     */
    public function memberships()
    {
        return $this->hasMany(TeamMembership::class);
    }

    /**
     * Get users assigned to this team through memberships.
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_memberships', 'team_id', 'user_id')
            ->withPivot(['tenant_id', 'status', 'status_before_disable'])
            ->withTimestamps();
    }
}
