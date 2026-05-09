<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

class WorkflowTemplate extends Model
{
    protected $fillable = [
        'tenant_id',
        'created_by_id',
        'name',
        'description',
        'category',
        'definition',
        'is_active',
        'usage_count',
    ];

    protected function casts(): array
    {
        return [
            'definition' => 'array',
            'is_active' => 'boolean',
            'usage_count' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
