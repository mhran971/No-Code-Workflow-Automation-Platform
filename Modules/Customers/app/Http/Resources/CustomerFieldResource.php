<?php

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\CustomerField;

/**
 * @mixin CustomerField
 */
class CustomerFieldResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'is_required' => $this->is_required,
            'options' => $this->options,
            'default_value' => $this->default_value,
            'sort_order' => $this->sort_order,
        ];
    }
}
