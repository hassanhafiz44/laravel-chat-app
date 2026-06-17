<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StripeAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'stripe_id' => $this->stripe_id,
            'card_brand' => $this->card_brand,
            'card_last_four' => $this->card_last_four,
        ];
    }
}
