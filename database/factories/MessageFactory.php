<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'role' => fake()->randomElement(['user', 'assistant']),
            'content' => fake()->paragraph(),
            'status' => 'pending',
        ];
    }

    public function fromUser(): static
    {
        return $this->state(['role' => 'user']);
    }

    public function fromAssistant(): static
    {
        return $this->state(['role' => 'assistant']);
    }

    public function pending(): static
    {
        return $this->state(['status' => 'pending', 'content' => null]);
    }

    public function done(): static
    {
        return $this->state(['status' => 'done']);
    }

    public function failed(): static
    {
        return $this->state(['status' => 'failed', 'error' => 'Simulated failure']);
    }
}
