<?php

namespace Modules\Team\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Auth\Models\User;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
    ];

    /**
     * Get the members belonging to the team.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Scope a query to only include teams of a given tenant.
     */
    public function scopeForTenant($query, $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope a query to search teams by name.
     */
    public function scopeSearch($query, $search)
    {
        if ($search) {
            // PostgreSQL ILIKE for case-insensitive search
            return $query->where('name', 'ILIKE', "%{$search}%");
        }
        return $query;
    }
}
