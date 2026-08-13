<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Customers\Enums\CustomerFieldType;

class CustomerField extends Model
{
    protected $fillable = [
        'tenant_id',
        'key',
        'label',
        'type',
        'is_required',
        'options',
        'default_value',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerFieldType::class,
            'is_required' => 'boolean',
            'options' => 'array',
            'default_value' => 'json',
            'sort_order' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
