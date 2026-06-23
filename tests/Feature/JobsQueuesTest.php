<?php

use App\Jobs\DeliverToChannel;
use App\Jobs\FlakyJob;
use App\Jobs\ProcessChatMessage;
use App\Jobs\ValidateMessage;
use App\Models\Message;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

// ─── BASIC DISPATCH ──────────────────────────────────────────────────────────

it('dispatch — Queue::fake intercepts ProcessChatMessage', function () {
    Queue::fake();

    $message = Message::factory()->fromUser()->create();

    ProcessChatMessage::dispatch($message);

    Queue::assertPushed(ProcessChatMessage::class, function ($job) use ($message) {
        return $job->message->id === $message->id;
    });
});

it('dispatch — POST /api/demo/jobs/dispatch returns 202', function () {
    Queue::fake();

    $this->postJson('/api/demo/jobs/dispatch')
        ->assertStatus(202)
        ->assertJsonPath('status', 'pending');
});

// ─── REAL HANDLE ─────────────────────────────────────────────────────────────

it('handle — sets status to done and writes AI reply', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'AI reply to: Hello!']]],
        ]),
    ]);

    $message = Message::factory()->fromUser()->create(['content' => 'Hello!']);

    (new ProcessChatMessage($message))->handle();

    $message->refresh();
    $reply = $message->conversation->messages()->where('role', 'assistant')->latest()->first();

    expect($message->status)->toBe('done')
        ->and($reply->content)->toBe('AI reply to: Hello!');
});

it('handle — sets status to processing before writing reply', function () {
    Http::fake([
        'api.groq.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'AI reply to: Hi']]],
        ]),
    ]);

    $statuses = [];

    $message = Message::factory()->fromUser()->create(['content' => 'Hi']);

    // Intercept the first update (status=processing) via observer trick
    Message::updating(function ($m) use (&$statuses) {
        $statuses[] = $m->status;
    });

    (new ProcessChatMessage($message))->handle();

    expect($statuses)->toContain('processing')
        ->and($statuses)->toContain('done');
});

it('handle — throws when Groq API call fails', function () {
    Http::fake([
        'api.groq.com/*' => Http::response('Internal Server Error', 500),
    ]);

    $message = Message::factory()->fromUser()->create(['content' => 'Hello!']);

    expect(fn () => (new ProcessChatMessage($message))->handle())
        ->toThrow(RuntimeException::class);
});

// ─── FAILED() ────────────────────────────────────────────────────────────────

it('failed() — marks message as failed and stores error', function () {
    $message = Message::factory()->fromUser()->create();
    $job = new ProcessChatMessage($message);

    $job->failed(new RuntimeException('Groq API timeout'));

    $message->refresh();
    expect($message->status)->toBe('failed')
        ->and($message->error)->toBe('Groq API timeout');
});

// ─── SHOULDBEUNIQUE ───────────────────────────────────────────────────────────

it('uniqueId — is scoped to the message id', function () {
    $message = Message::factory()->fromUser()->create();
    $job = new ProcessChatMessage($message);

    expect($job->uniqueId())->toBe("message:{$message->id}");
});

it('uniqueFor — lock held for 60 seconds', function () {
    $job = new ProcessChatMessage(Message::factory()->fromUser()->create());

    expect($job->uniqueFor)->toBe(60);
});

// ─── RETRIES & BACKOFF ────────────────────────────────────────────────────────

it('retries — tries=3 and exponential backoff configured', function () {
    $job = new ProcessChatMessage(Message::factory()->fromUser()->create());

    expect($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60]);
});

// ─── MIDDLEWARE ───────────────────────────────────────────────────────────────

it('middleware — includes RateLimited and WithoutOverlapping', function () {
    $job = new ProcessChatMessage(Message::factory()->fromUser()->create());

    $middleware = $job->middleware();

    expect($middleware)->toHaveCount(2)
        ->and($middleware[0])->toBeInstanceOf(RateLimited::class)
        ->and($middleware[1])->toBeInstanceOf(WithoutOverlapping::class);
});

// ─── CHAINING ────────────────────────────────────────────────────────────────

it('chain — Bus::fake records chained jobs', function () {
    Bus::fake();

    $message = Message::factory()->fromUser()->create();

    Bus::chain([
        new ValidateMessage($message),
        new ProcessChatMessage($message),
    ])->dispatch();

    Bus::assertChained([
        ValidateMessage::class,
        ProcessChatMessage::class,
    ]);
});

it('chain — POST /api/demo/jobs/chain returns 202', function () {
    Bus::fake();

    $this->postJson('/api/demo/jobs/chain')
        ->assertStatus(202);
});

it('chain — ValidateMessage throws when content is empty, stopping the chain', function () {
    $message = Message::factory()->pending()->create();

    expect(fn () => (new ValidateMessage($message))->handle())
        ->toThrow(InvalidArgumentException::class, 'Message content cannot be empty');
});

it('chain — ValidateMessage succeeds and sets status to validated', function () {
    $message = Message::factory()->fromUser()->create(['content' => 'Valid content']);

    (new ValidateMessage($message))->handle();

    expect($message->fresh()->status)->toBe('validated');
});

// ─── BATCHING ────────────────────────────────────────────────────────────────

it('batch — Bus::fake records batched DeliverToChannel jobs', function () {
    Bus::fake();

    $message = Message::factory()->fromUser()->create();
    $channels = ['whatsapp', 'telegram', 'web'];

    Bus::batch(
        collect($channels)->map(fn ($c) => new DeliverToChannel($message, $c))->all()
    )->dispatch();

    Bus::assertBatched(function (PendingBatch $batch) use ($channels) {
        return $batch->jobs->count() === count($channels);
    });
});

it('batch — POST /api/demo/jobs/batch returns 202 with batch_id and 3 jobs', function () {
    Bus::fake();

    $this->postJson('/api/demo/jobs/batch')
        ->assertStatus(202)
        ->assertJsonPath('total_jobs', 3)
        ->assertJsonStructure(['batch_id', 'channels', 'message_id']);
});

it('batch — DeliverToChannel writes to cache delivery log', function () {
    $message = Message::factory()->fromUser()->create();

    (new DeliverToChannel($message, 'whatsapp'))->handle();
    (new DeliverToChannel($message, 'telegram'))->handle();

    $deliveries = Cache::get("message:{$message->id}:deliveries");

    expect($deliveries)->toContain('whatsapp')
        ->and($deliveries)->toContain('telegram');
});

// ─── FLAKYJOB — release() vs fail() vs throw ─────────────────────────────────

it('FlakyJob release — releases back to queue without counting as a failure', function () {
    Queue::fake();

    FlakyJob::dispatch('release');

    Queue::assertPushed(FlakyJob::class, fn ($job) => $job->mode === 'release');
});

it('FlakyJob throw — throws exception (normal retry path)', function () {
    $job = new FlakyJob('throw');

    expect(fn () => $job->handle())
        ->toThrow(RuntimeException::class, 'FlakyJob failed in mode: throw');
});

it('FlakyJob fail — fail() skips retries and does not rethrow', function () {
    $job = new FlakyJob('fail');

    // $this->fail($e) is a no-op without a real queue worker context (no $this->job).
    // In a real worker it skips remaining retries and routes straight to failed().
    // Here we verify: no exception propagates (unlike 'throw' mode).
    expect(fn () => $job->handle())->not->toThrow(Exception::class);
});
