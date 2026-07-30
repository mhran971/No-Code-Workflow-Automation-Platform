<?php

namespace Modules\Workflows\Transformers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NodeConfigResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type?->value,
            'placeholder' => $this->placeholder,
            'options' => $this->options,
            'required' => (bool) $this->is_required,
            'default_value' => $this->default_value,
        ];
    }
}
