<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Workflows\Enums\NodeConfigFieldType;

class NodeConfigField extends Model
{
    protected $fillable = [
        'node_id',
        'key',
        'label',
        'type',
        'placeholder',
        'options',
        'is_required',
        'default_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'is_required' => 'boolean',
            'default_value' => 'json',
            'sort_order' => 'integer',
            'type' => NodeConfigFieldType::class,
        ];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
