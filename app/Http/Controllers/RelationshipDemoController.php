<?php

namespace App\Http\Controllers;

use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\PostResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RelationshipDemoController extends Controller
{
    /**
     * HasOne — User has one Profile
     */
    public function hasOne(): UserResource
    {
        $user = User::with('profile')->first();

        return UserResource::make($user);
    }

    /**
     * HasMany — Conversation has many Messages (+ latestMessage, userMessages)
     */
    public function hasMany(): ConversationResource
    {
        $conversation = Conversation::with([
            'messages',
            'latestMessage',
            'oldestMessage',
            'userMessages',
        ])->withCount('messages')->first();

        return ConversationResource::make($conversation);
    }

    /**
     * BelongsTo — Message belongs to Conversation
     */
    public function belongsTo(): MessageResource
    {
        $message = Message::with('conversation')->first();

        return MessageResource::make($message);
    }

    /**
     * BelongsToMany — User belongs to many Teams with pivot role
     */
    public function belongsToMany(): UserResource
    {
        $user = User::with('teams')->first();

        return UserResource::make($user);
    }

    /**
     * HasManyThrough — User gets all Messages through Conversations
     */
    public function hasManyThrough(): UserResource
    {
        $user = User::with('messages')->first();

        return UserResource::make($user);
    }

    /**
     * HasOneThrough — User gets StripeAccount through Subscription
     */
    public function hasOneThrough(): UserResource
    {
        $user = User::with('stripeAccount')->first();

        return UserResource::make($user);
    }

    /**
     * MorphMany — Conversation and Post both have Media
     */
    public function morphMany(): JsonResponse
    {
        $conversation = Conversation::with('media')->first();
        $post = Post::with('media')->first();

        return response()->json([
            'explanation' => 'Both Conversation and Post share the media table via polymorphic MorphMany',
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'media' => $conversation->media->map(fn ($m) => ['id' => $m->id, 'url' => $m->url, 'mediable_type' => $m->mediable_type]),
            ],
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'media' => $post->media->map(fn ($m) => ['id' => $m->id, 'url' => $m->url, 'mediable_type' => $m->mediable_type]),
            ],
        ]);
    }

    /**
     * MorphToMany / MorphedByMany — Post and Conversation share Tags via taggables pivot
     */
    public function morphToMany(): JsonResponse
    {
        $post = Post::with('tags')->first();
        $conversation = Conversation::with('tags')->first();
        $tag = Tag::with(['posts', 'conversations'])->first();

        return response()->json([
            'explanation' => 'Tags are polymorphically attached to both Posts and Conversations via the taggables table',
            'post_tags' => [
                'post_id' => $post->id,
                'tags' => $post->tags->pluck('name'),
            ],
            'conversation_tags' => [
                'conversation_id' => $conversation->id,
                'tags' => $conversation->tags->pluck('name'),
            ],
            'tag_parents' => [
                'tag' => $tag->name,
                'posts_count' => $tag->posts->count(),
                'conversations_count' => $tag->conversations->count(),
            ],
        ]);
    }

    /**
     * Existence queries — withCount, has, whereHas, doesntHave
     */
    public function existence(): JsonResponse
    {
        // withCount: attach message count to each conversation
        $conversationsWithCount = Conversation::withCount('messages')
            ->get()
            ->map(fn ($c) => ['id' => $c->id, 'title' => $c->title, 'messages_count' => $c->messages_count]);

        // has: conversations that have at least 1 tag
        $withTags = Conversation::has('tags')->count();

        // whereHas: conversations where channel is 'web'
        $webConversations = Conversation::whereHas('messages', fn ($q) => $q->where('role', 'assistant'))->count();

        // doesntHave: posts with no media
        $postsWithNoMedia = Post::doesntHave('media')->count();

        return response()->json([
            'conversations_with_message_count' => $conversationsWithCount,
            'conversations_with_at_least_one_tag' => $withTags,
            'conversations_with_assistant_reply' => $webConversations,
            'posts_with_no_media' => $postsWithNoMedia,
        ]);
    }
}
