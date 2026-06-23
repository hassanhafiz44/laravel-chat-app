<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

        $reply = $this->fetchGroqReply();

        $this->message->conversation->messages()->create([
            'role' => 'assistant',
            'content' => $reply,
            'status' => 'done',
        ]);

        $this->message->update(['status' => 'done']);
    }

    /** Calls Groq's OpenAI-compatible chat completions endpoint */
    protected function fetchGroqReply(): string
    {
        $response = Http::withToken(config('services.groq.key'))
            ->timeout(60)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model' => config('services.groq.model'),
                'messages' => [
                    ['role' => 'user', 'content' => $this->message->content],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException("Groq API error ({$response->status()}): {$response->body()}");
        }

        return $response->json('choices.0.message.content') ?? '';
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
