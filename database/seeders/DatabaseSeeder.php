<?php

namespace Database\Seeders;

use App\Models\Conversation;
use App\Models\Media;
use App\Models\Post;
use App\Models\Profile;
use App\Models\StripeAccount;
use App\Models\Subscription;
use App\Models\Tag;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // 3 Teams
        $teams = Team::factory(3)->create();

        // 5 Tags for poly many-to-many
        $tags = Tag::factory(5)->create();

        // 5 Users with full relationship graph
        User::factory(5)->create()->each(function (User $user) use ($teams, $tags) {
            // HasOne: Profile
            Profile::factory()->create(['user_id' => $user->id]);

            // HasOne through HasOne: Subscription + StripeAccount
            $subscription = Subscription::factory()->create(['user_id' => $user->id]);
            StripeAccount::factory()->create(['subscription_id' => $subscription->id]);

            // BelongsToMany with pivot: 1–2 Teams
            $userTeams = $teams->random(rand(1, 2));
            $user->teams()->attach($userTeams->mapWithKeys(fn ($team, $i) => [
                $team->id => ['role' => $i === 0 ? 'admin' : 'member'],
            ]));

            // HasMany: 2–3 Conversations each with messages + media + tags
            Conversation::factory(rand(2, 3))->create(['user_id' => $user->id])
                ->each(function (Conversation $conv) use ($tags) {
                    // HasMany: 4–6 Messages (mix of roles)
                    $conv->messages()->createMany(
                        collect(range(1, rand(4, 6)))->map(fn ($i) => [
                            'role' => $i % 2 === 0 ? 'assistant' : 'user',
                            'content' => fake()->paragraph(),
                        ])->all()
                    );

                    // MorphMany: Media
                    $conv->media()->create(['url' => fake()->imageUrl(640, 480)]);

                    // MorphToMany: random 2 Tags
                    $conv->tags()->attach($tags->random(2)->pluck('id'));
                });

            // HasMany: 1–2 Posts each with media + tags
            Post::factory(rand(1, 2))->create(['user_id' => $user->id])
                ->each(function (Post $post) use ($tags) {
                    $post->media()->create(['url' => fake()->imageUrl(800, 600)]);
                    $post->tags()->attach($tags->random(2)->pluck('id'));
                });
        });
    }
}
