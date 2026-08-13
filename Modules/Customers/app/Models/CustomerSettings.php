<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;
use Modules\Customers\Enums\CustomerLinkingField;

class CustomerSettings extends Model
{
    protected $fillable = [
        'tenant_id',
        'linking_field',
    ];

    protected function casts(): array
    {
        return [
            'linking_field' => CustomerLinkingField::class,
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
