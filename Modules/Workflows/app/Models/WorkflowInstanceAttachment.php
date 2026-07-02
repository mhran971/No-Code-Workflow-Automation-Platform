<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class WorkflowInstanceAttachment extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'workflow_instance_id',
        'uploaded_by',
        'file_name',
        'file_path',
        'mime_type',
        'size_bytes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function workflowInstance(): BelongsTo
    {
        return $this->belongsTo(WorkflowInstance::class, 'workflow_instance_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
