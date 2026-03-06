<?php

namespace Modules\Team\app\Http\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'member_count' => $this->whenCounted('members', $this->members_count ?? $this->members()->count()),
            'members' => $this->whenLoaded('members', function () {
                return $this->members->map(function ($member) {
                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'pivot' => [
                            'role' => $member->pivot->role,
                        ],
                    ];
                });
            }),
            'created_at' => $this->whenNotNull($this->created_at?->toIso8601String()),
            'updated_at' => $this->whenNotNull($this->updated_at?->toIso8601String()),
        ];
    }
}
