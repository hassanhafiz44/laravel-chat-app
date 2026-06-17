<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Demonstrates: ShouldBeUnique, retries, exponential backoff, timeout, failed(), middleware
 */
class ProcessChatMessage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    public int $timeout = 120;

    public int $uniqueFor = 60;

    public function __construct(public readonly Message $message) {}

    /** Only one job per message ID in the queue at a time */
    public function uniqueId(): string
    {
        return "message:{$this->message->id}";
    }

    /** Rate-limit Groq calls + prevent two replies generating for the same conversation */
    public function middleware(): array
    {
        return [
            new RateLimited('groq'),
            new WithoutOverlapping($this->message->conversation_id),
        ];
    }

    public function handle(): void
    {
        $this->message->update(['status' => 'processing']);

        // Simulate AI work — replace with real Groq/OpenAI call
        $reply = "AI reply to: {$this->message->content}";

        $this->message->conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
            'status' => 'done',
        ]);

        $this->message->update(['status' => 'done']);
    }

    /** Called after all retries are exhausted */
    public function failed(\Throwable $e): void
    {
        $this->message->update([
            'status' => 'failed',
            'error' => $e->getMessage(),
        ]);
    }
}
