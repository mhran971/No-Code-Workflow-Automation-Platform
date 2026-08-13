<?php

namespace Modules\Customers\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\Tenant;

class Customer extends Model
{
    protected $fillable = [
        'tenant_id',
        'email',
        'phone',
        'first_name',
        'last_name',
        'custom_field_values',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'custom_field_values' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Flattened core + custom fields for the workflow template `customer.*` namespace
     * (see Modules\Workflows\Services\Execution\NodeExecutionContext::resolutionScope()).
     * Fixed columns win on key collision with a custom field of the same name — CustomerField
     * keys are validated at write time to exclude these reserved names.
     */
    public function toTemplateArray(): array
    {
        return array_merge($this->custom_field_values ?? [], [
            'email' => $this->email,
            'phone' => $this->phone,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
        ]);
    }
}
