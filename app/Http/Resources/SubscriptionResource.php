<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'plan' => $this->plan,
            'status' => $this->status,
            'stripe_account' => StripeAccountResource::make($this->whenLoaded('stripeAccount')),
        ];
    }
}
