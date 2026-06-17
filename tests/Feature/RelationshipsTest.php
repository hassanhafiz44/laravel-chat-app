<?php

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Message;
use App\Models\Post;
use App\Models\Profile;
use App\Models\StripeAccount;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('hasOne — user has one profile', function () {
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);

    expect($user->profile)->toBeInstanceOf(Profile::class)
        ->and($user->profile->user_id)->toBe($user->id);
});

it('belongsTo — profile belongs to user', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->create(['user_id' => $user->id]);

    expect($profile->user)->toBeInstanceOf(User::class)
        ->and($profile->user->id)->toBe($user->id);
});

it('hasMany — conversation has many messages', function () {
    $conversation = Conversation::factory()->create();
    Message::factory(4)->create(['conversation_id' => $conversation->id]);

    expect($conversation->messages)->toHaveCount(4);
});

it('hasMany — latestOfMany returns most recent message', function () {
    $conversation = Conversation::factory()->create();
    Message::factory(3)->create(['conversation_id' => $conversation->id]);
    $latest = Message::factory()->create(['conversation_id' => $conversation->id]);

    expect($conversation->latestMessage->id)->toBe($latest->id);
});

it('hasMany — userMessages scoped relationship', function () {
    $conversation = Conversation::factory()->create();
    Message::factory(3)->fromUser()->create(['conversation_id' => $conversation->id]);
    Message::factory(2)->fromAssistant()->create(['conversation_id' => $conversation->id]);

    expect($conversation->userMessages)->toHaveCount(3);
});

it('belongsToMany — user belongs to teams with pivot role', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $user->teams()->attach($team->id, ['role' => 'admin']);

    $loadedTeam = $user->teams()->first();

    expect($loadedTeam->id)->toBe($team->id)
        ->and($loadedTeam->pivot->role)->toBe('admin');
});

it('belongsToMany — sync replaces team memberships', function () {
    $user = User::factory()->create();
    $teams = Team::factory(3)->create();

    $user->teams()->attach($teams[0]->id);
    $user->teams()->sync([$teams[1]->id, $teams[2]->id]);

    expect($user->teams()->pluck('teams.id')->toArray())
        ->toEqualCanonicalizing([$teams[1]->id, $teams[2]->id]);
});

it('hasManyThrough — user gets all messages through conversations', function () {
    $user = User::factory()->create();
    $conv1 = Conversation::factory()->create(['user_id' => $user->id]);
    $conv2 = Conversation::factory()->create(['user_id' => $user->id]);
    Message::factory(3)->create(['conversation_id' => $conv1->id]);
    Message::factory(2)->create(['conversation_id' => $conv2->id]);

    expect($user->messages()->count())->toBe(5);
});

it('hasOneThrough — user gets stripe account through subscription', function () {
    $user = User::factory()->create();
    $subscription = Subscription::factory()->create(['user_id' => $user->id]);
    $stripe = StripeAccount::factory()->create(['subscription_id' => $subscription->id]);

    expect($user->stripeAccount)->toBeInstanceOf(StripeAccount::class)
        ->and($user->stripeAccount->id)->toBe($stripe->id);
});

it('morphMany — conversation has media attached', function () {
    $conversation = Conversation::factory()->create();
    $conversation->media()->create(['url' => 'https://example.com/audio.mp3']);

    expect($conversation->media)->toHaveCount(1)
        ->and($conversation->media->first()->mediable_type)->toBe(Conversation::class);
});

it('morphMany — post has media attached', function () {
    $post = Post::factory()->create();
    $post->media()->create(['url' => 'https://example.com/image.jpg']);

    expect($post->media)->toHaveCount(1)
        ->and($post->media->first()->mediable_type)->toBe(Post::class);
});

it('morphTo — media resolves back to its parent', function () {
    $post = Post::factory()->create();
    $media = $post->media()->create(['url' => 'https://example.com/file.pdf']);

    $freshMedia = Media::find($media->id);

    expect($freshMedia->mediable)->toBeInstanceOf(Post::class)
        ->and($freshMedia->mediable->id)->toBe($post->id);
});

it('morphToMany — post can have tags', function () {
    $post = Post::factory()->create();
    $tags = Tag::factory(2)->create();
    $post->tags()->attach($tags->pluck('id'));

    expect($post->tags)->toHaveCount(2);
});

it('morphedByMany — tag knows its posts and conversations', function () {
    $tag = Tag::factory()->create();
    $post = Post::factory()->create();
    $conversation = Conversation::factory()->create();

    $post->tags()->attach($tag->id);
    $conversation->tags()->attach($tag->id);

    expect($tag->posts)->toHaveCount(1)
        ->and($tag->conversations)->toHaveCount(1);
});

it('existence — has filters conversations with messages', function () {
    $withMessages = Conversation::factory()->create();
    Message::factory()->create(['conversation_id' => $withMessages->id]);
    Conversation::factory()->create(); // no messages

    expect(Conversation::has('messages')->count())->toBe(1);
});

it('existence — whereHas filters by related model attributes', function () {
    $conv = Conversation::factory()->create();
    Message::factory()->fromAssistant()->create(['conversation_id' => $conv->id]);
    $emptyConv = Conversation::factory()->create();

    $result = Conversation::whereHas('messages', fn ($q) => $q->where('role', 'assistant'))->count();

    expect($result)->toBe(1);
});

it('existence — doesntHave finds posts with no media', function () {
    Post::factory()->create(); // no media
    $postWithMedia = Post::factory()->create();
    $postWithMedia->media()->create(['url' => 'https://example.com/img.jpg']);

    expect(Post::doesntHave('media')->count())->toBe(1);
});

it('GET /api/demo/has-one returns 200 with profile', function () {
    $user = User::factory()->create();
    Profile::factory()->create(['user_id' => $user->id]);

    $this->getJson('/api/demo/has-one')
        ->assertOk()
        ->assertJsonPath('data.profile.id', $user->profile->id);
});

it('GET /api/demo/has-many returns 200 with messages', function () {
    $conv = Conversation::factory()->create();
    Message::factory(3)->create(['conversation_id' => $conv->id]);

    $this->getJson('/api/demo/has-many')
        ->assertOk()
        ->assertJsonStructure(['data' => ['messages', 'latest_message', 'oldest_message', 'user_messages']]);
});

it('GET /api/demo/belongs-to returns 200 with conversation', function () {
    $conv = Conversation::factory()->create();
    Message::factory()->create(['conversation_id' => $conv->id]);

    $this->getJson('/api/demo/belongs-to')
        ->assertOk()
        ->assertJsonStructure(['data' => ['conversation']]);
});

it('GET /api/demo/belongs-to-many returns 200 with teams and pivot', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team->id, ['role' => 'admin']);

    $this->getJson('/api/demo/belongs-to-many')
        ->assertOk()
        ->assertJsonStructure(['data' => ['teams']]);
});

it('GET /api/demo/has-many-through returns 200 with messages', function () {
    $user = User::factory()->create();
    $conv = Conversation::factory()->create(['user_id' => $user->id]);
    Message::factory(2)->create(['conversation_id' => $conv->id]);

    $this->getJson('/api/demo/has-many-through')
        ->assertOk()
        ->assertJsonStructure(['data' => ['messages']]);
});

it('GET /api/demo/has-one-through returns 200 with stripe_account', function () {
    $user = User::factory()->create();
    $sub = Subscription::factory()->create(['user_id' => $user->id]);
    StripeAccount::factory()->create(['subscription_id' => $sub->id]);

    $this->getJson('/api/demo/has-one-through')
        ->assertOk()
        ->assertJsonStructure(['data' => ['stripe_account']]);
});

it('GET /api/demo/morph-many returns 200', function () {
    $conv = Conversation::factory()->create();
    $conv->media()->create(['url' => 'https://example.com/a.jpg']);
    $post = Post::factory()->create();
    $post->media()->create(['url' => 'https://example.com/b.jpg']);

    $this->getJson('/api/demo/morph-many')
        ->assertOk()
        ->assertJsonStructure(['conversation', 'post']);
});

it('GET /api/demo/morph-to-many returns 200', function () {
    $tag = Tag::factory()->create();
    $post = Post::factory()->create();
    $post->tags()->attach($tag->id);
    $conv = Conversation::factory()->create();
    $conv->tags()->attach($tag->id);

    $this->getJson('/api/demo/morph-to-many')
        ->assertOk()
        ->assertJsonStructure(['post_tags', 'conversation_tags', 'tag_parents']);
});

it('GET /api/demo/existence returns 200', function () {
    $conv = Conversation::factory()->create();
    Message::factory()->create(['conversation_id' => $conv->id]);

    $this->getJson('/api/demo/existence')
        ->assertOk()
        ->assertJsonStructure(['conversations_with_message_count', 'conversations_with_at_least_one_tag']);
});
