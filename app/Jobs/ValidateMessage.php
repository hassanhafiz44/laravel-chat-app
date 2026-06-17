<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Demonstrates: Bus::chain() — first link; throws to stop the whole chain
 */
class ValidateMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Message $message) {}

    public function handle(): void
    {
        if (empty($this->message->content)) {
            throw new \InvalidArgumentException("Message content cannot be empty — chain stopped.");
        }

        $this->message->update(['status' => 'validated']);
    }
}
