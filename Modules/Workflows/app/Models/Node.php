<?php

namespace Modules\Workflows\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
// use Modules\Workflows\Database\Factories\NodeFactory;
use Modules\Workflows\Enums\NodeCategory;

class Node extends Model
{
    protected $fillable = [
        'type',
        'label',
        'category',
        'description',
        'color',
        'icon',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'category' => NodeCategory::class,
            'is_active' => 'boolean',
        ];
    }

    public function configFields(): HasMany
    {
        return $this->hasMany(NodeConfigField::class)->orderBy('sort_order');
    }
}
