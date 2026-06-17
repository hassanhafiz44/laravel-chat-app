<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Demonstrates: release() vs fail() vs throw — three distinct failure modes
 *
 * - 'release': back to queue after delay, attempt counter NOT incremented
 * - 'fail':    skip remaining retries, call failed() immediately
 * - 'throw':   normal failure — retry after backoff, counter incremented
 */
class FlakyJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly string $mode = 'throw') {}

    public function handle(): void
    {
        $e = new \RuntimeException("FlakyJob failed in mode: {$this->mode}");

        match ($this->mode) {
            'release' => $this->release(10),
            'fail'    => $this->fail($e),
            default   => throw $e,
        };
    }

    public function failed(\Throwable $e): void
    {
        // Intentionally left minimal — tests assert this was called
    }
}
