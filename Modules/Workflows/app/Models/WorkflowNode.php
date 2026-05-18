<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// use Modules\Workflows\Database\Factories\WorkflowNodeFactory;

class WorkflowNode extends Model
{
    protected $fillable = [
        'workflow_id',
        'node_id',
        'version_id',
        'key',
        'label',
        'position_x',
        'position_y',
        'config',
        'is_entry_point',
        'is_terminal',
    ];

    protected function casts(): array
    {
        return [
            'config'         => 'array',
            'position_x'     => 'float',
            'position_y'     => 'float',
            'is_entry_point' => 'boolean',
            'is_terminal'    => 'boolean',
        ];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function nodeType(): BelongsTo
    {
        return $this->belongsTo(Node::class, 'node_id');
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class, 'version_id');
    }

    public function outgoingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'source_node_key', 'key')
                    ->where('workflow_id', $this->workflow_id);
    }

    public function incomingEdges(): HasMany
    {
        return $this->hasMany(WorkflowEdge::class, 'target_node_key', 'key')
                    ->where('workflow_id', $this->workflow_id);
    }

    /**
     * Validate config against the node type's required fields.
     */
    public function validateConfig(): array // returns array of missing/invalid keys
    {
        $errors = [];
        $fields = $this->nodeType->configFields;

        foreach ($fields as $field) {
            if ($field->is_required && blank($this->config[$field->key] ?? null)) {
                $errors[] = $field->key;
            }
        }

        return $errors;
    }
}
