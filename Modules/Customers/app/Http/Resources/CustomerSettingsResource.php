<?php

namespace Modules\Customers\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Customers\Models\CustomerSettings;

/**
 * @mixin CustomerSettings
 */
class CustomerSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'linking_field' => $this->linking_field,
        ];
    }
}
