<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'profile' => ProfileResource::make($this->whenLoaded('profile')),
            'subscription' => SubscriptionResource::make($this->whenLoaded('subscription')),
            'stripe_account' => StripeAccountResource::make($this->whenLoaded('stripeAccount')),
            'teams' => TeamResource::collection($this->whenLoaded('teams')),
            'conversations' => ConversationResource::collection($this->whenLoaded('conversations')),
            'posts' => PostResource::collection($this->whenLoaded('posts')),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'pivot_role' => $this->whenPivotLoaded('team_user', fn () => $this->pivot->role),
        ];
    }
}
