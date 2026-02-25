<?php

namespace Modules\Team\Transformers\Modules\Team\App\Http\Resource;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     * Input JSON: (Internal Model Data)
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'member_count' => $this->members->count(),
            'members' => $this->members->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    // Logic: Avatar or Initials
                    'avatar' => $member->avatar ?? $this->generateInitials($member->name),
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * Generate initials from name.
     * Input JSON: { "name": "John Doe" }
     *
     * @param string $name
     * @return string
     */
    private function generateInitials(string $name): string
    {
        $words = explode(' ', $name);
        $initials = '';
        foreach ($words as $word) {
            $initials .= strtoupper(substr($word, 0, 1));
        }
        return substr($initials, 0, 2); // Max 2 letters
    }
}
