<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessChatMessage;
use App\Models\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4096'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'conversation_id' => ['nullable', 'integer', 'exists:conversations,id'],
        ]);

        $conversation = isset($validated['conversation_id'])
            ? Conversation::find($validated['conversation_id'])
            : Conversation::create(['user_id' => $validated['user_id'], 'title' => 'New Conversation']);

        $message = $conversation->messages()->create([
            'role' => 'user',
            'content' => $validated['message'],
            'status' => 'pending',
        ]);

        ProcessChatMessage::dispatch($message);

        return response()->json([
            'conversation_id' => $conversation->id,
            'message_id' => $message->id,
            'status' => 'queued',
        ], 202);
    }

    public function messages(Conversation $conversation): JsonResponse
    {
        return response()->json(
            $conversation->messages()
                ->orderBy('created_at')
                ->get(['id', 'role', 'content', 'status', 'created_at'])
        );
    }
}
