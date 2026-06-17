<?php

namespace Database\Factories;

use App\Models\StripeAccount;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StripeAccount>
 */
class StripeAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'subscription_id' => Subscription::factory(),
            'stripe_id' => 'acct_'.fake()->bothify('??##??##??##'),
            'card_brand' => fake()->randomElement(['visa', 'mastercard', 'amex']),
            'card_last_four' => (string) fake()->numberBetween(1000, 9999),
        ];
    }
}
