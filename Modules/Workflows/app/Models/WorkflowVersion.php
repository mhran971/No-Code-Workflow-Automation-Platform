<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Auth\Models\User;

class WorkflowVersion extends Model
{
    protected $fillable = [
        'workflow_id',
        'tenant_id',
        'version_number',
        'version_label',
        'definition',
        'release_note',
        'published_by_id',
        'published_at',
        'rollback_source_version_id',
    ];

    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'definition' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_id');
    }

    public function rollbackSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rollback_source_version_id');
    }
}
