<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

/**
 * Demonstrates: Bus::batch() — runs in parallel per-channel, tracked as a unit
 */
class DeliverToChannel implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Message $message,
        public readonly string $channel
    ) {}

    public function handle(): void
    {
        // Track which channels successfully received the message
        $key = "message:{$this->message->id}:deliveries";

        $deliveries = Cache::get($key, []);
        $deliveries[] = $this->channel;

        Cache::put($key, $deliveries, now()->addMinutes(10));
    }
}
