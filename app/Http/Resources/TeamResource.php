<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'pivot_role' => $this->whenPivotLoaded('team_user', fn () => $this->pivot->role),
            'users' => UserResource::collection($this->whenLoaded('users')),
        ];
    }
}
