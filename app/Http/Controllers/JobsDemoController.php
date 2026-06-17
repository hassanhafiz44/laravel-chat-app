<?php

namespace App\Http\Controllers;

use App\Jobs\DeliverToChannel;
use App\Jobs\ProcessChatMessage;
use App\Jobs\ValidateMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;

class JobsDemoController extends Controller
{
    /**
     * Dispatch — demonstrates basic job dispatch with afterCommit()
     * POST /api/demo/jobs/dispatch
     */
    public function dispatch(): JsonResponse
    {
        $message = Message::factory()->fromUser()->create([
            'conversation_id' => Conversation::factory()->create()->id,
        ]);

        ProcessChatMessage::dispatch($message)->afterCommit();

        return response()->json([
            'message_id' => $message->id,
            'status' => $message->status,
            'explanation' => 'Job dispatched. Run `php artisan queue:work --once` to process it.',
        ], 202);
    }

    /**
     * Chain — demonstrates Bus::chain() sequential execution
     * POST /api/demo/jobs/chain
     */
    public function chain(): JsonResponse
    {
        $message = Message::factory()->fromUser()->create([
            'conversation_id' => Conversation::factory()->create()->id,
        ]);

        Bus::chain([
            new ValidateMessage($message),
            new ProcessChatMessage($message),
        ])->dispatch();

        return response()->json([
            'message_id' => $message->id,
            'explanation' => 'ValidateMessage runs first. If it throws, ProcessChatMessage is cancelled.',
        ], 202);
    }

    /**
     * Batch — demonstrates Bus::batch() parallel execution with progress tracking
     * POST /api/demo/jobs/batch
     */
    public function batch(): JsonResponse
    {
        $message = Message::factory()->fromUser()->create([
            'conversation_id' => Conversation::factory()->create()->id,
        ]);

        $channels = ['whatsapp', 'telegram', 'web'];

        $batch = Bus::batch(
            collect($channels)->map(fn ($channel) => new DeliverToChannel($message, $channel))->all()
        )
            ->then(fn ($b) => \Illuminate\Support\Facades\Log::info('All channels delivered', ['batch' => $b->id]))
            ->catch(fn ($b, $e) => \Illuminate\Support\Facades\Log::error('Batch delivery failed', ['error' => $e->getMessage()]))
            ->dispatch();

        return response()->json([
            'message_id' => $message->id,
            'batch_id' => $batch->id,
            'total_jobs' => $batch->totalJobs,
            'channels' => $channels,
            'explanation' => 'All 3 DeliverToChannel jobs run in parallel. Track progress via batch_id.',
        ], 202);
    }
}
