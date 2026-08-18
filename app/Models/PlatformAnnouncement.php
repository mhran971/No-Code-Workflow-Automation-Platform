<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

class PlatformAnnouncement extends Model
{
    protected $fillable = [
        'title',
        'message',
        'type',
        'target_tenant_id',
        'is_active',
        'starts_at',
        'expires_at',
        'created_by_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActiveForTenant(Builder $query, ?int $tenantId = null): Builder
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->whereNull('target_tenant_id')
                    ->when($tenantId, fn ($subQ) => $subQ->orWhere('target_tenant_id', $tenantId));
            })
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', now());
            });
    }
}
