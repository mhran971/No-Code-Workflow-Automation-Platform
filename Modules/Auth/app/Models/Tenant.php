<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Auth\Enums\BusinessType;
use Modules\Team\Models\Team;
use Modules\Workflows\Models\Workflow;
use Modules\Workflows\Models\WorkflowInstance;

class Tenant extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'business_name',
        'business_type',
        'is_active',
        'maintenance_mode',
        'maintenance_message',
        'deactivated_at',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'business_type' => BusinessType::class,
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    /**
     * Get the users for the tenant.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the teams for the tenant.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the workflows for the tenant.
     */
    public function workflows(): HasMany
    {
        return $this->hasMany(Workflow::class);
    }

    /**
     * Get the workflow instances / runs for the tenant.
     */
    public function workflowInstances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }
}
