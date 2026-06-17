<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'channel' => $this->channel,
            'user' => UserResource::make($this->whenLoaded('user')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'latest_message' => MessageResource::make($this->whenLoaded('latestMessage')),
            'oldest_message' => MessageResource::make($this->whenLoaded('oldestMessage')),
            'user_messages' => MessageResource::collection($this->whenLoaded('userMessages')),
            'media' => MediaResource::collection($this->whenLoaded('media')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'messages_count' => $this->whenCounted('messages'),
        ];
    }
}
