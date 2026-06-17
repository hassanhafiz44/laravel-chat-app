<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'avatar' => $this->avatar,
            'timezone' => $this->timezone,
            'bio' => $this->bio,
            'user' => UserResource::make($this->whenLoaded('user')),
        ];
    }
}
