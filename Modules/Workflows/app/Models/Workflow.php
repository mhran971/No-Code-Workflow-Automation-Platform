<?php

namespace Modules\Workflows\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Str;
use Modules\Auth\Enums\Role;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;
use Modules\Team\Models\Team;
use Modules\Workflows\Enums\WorkflowStatus;

class Workflow extends Model
{
    protected static function booted(): void
    {
        static::creating(function (Workflow $workflow): void {
            $workflow->public_token ??= (string) Str::uuid();
        });
    }

    protected $fillable = [
        'tenant_id',
        'team_id',
        'template_id',
        'created_by_id',
        'name',
        'description',
        'status',
        'draft_definition',
        'draft_revision',
        'current_version_id',
        'current_version_number',
        'current_version_label',
        'total_runs',
        'active_instances',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => WorkflowStatus::class,
            'draft_definition' => 'array',
            'draft_revision' => 'integer',
            'current_version_number' => 'integer',
            'total_runs' => 'integer',
            'active_instances' => 'integer',
            'deleted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkflowTemplate::class, 'template_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'current_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function instances(): HasMany
    {
        return $this->hasMany(WorkflowInstance::class);
    }

    public function scopeVisibleTo(
        Builder $query,
        User $actor
    ): Builder {
        $query->where(
            'tenant_id',
            (int) $actor->tenant_id
        );

        return match ($actor->role) {
            Role::BusinessOwner => $query,

            Role::Manager => $query->where(
                'team_id',
                $actor->managedTeam->id
            ),

            Role::Employee => $query->whereHas(
                'team.memberships',
                function (Builder $query) use ($actor): void {
                    $query
                        ->where('tenant_id', (int) $actor->tenant_id)
                        ->where('user_id', (int) $actor->id)
                        ->where('status', 'active');
                }
            ),

            default => throw new AuthorizationException(
                'You are not allowed to view workflows.'
            ),
        };
    }
}
