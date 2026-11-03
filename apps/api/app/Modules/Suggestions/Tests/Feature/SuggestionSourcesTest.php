<?php

namespace App\Modules\Suggestions\Tests\Feature;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Goals\Models\Goal;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Services\SuggestionEngine;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionSourcesTest extends TestCase
{
    use RefreshDatabase;

    private function insightKeys(User $user, string $prefix): array
    {
        return Suggestion::whereBelongsTo($user)->where('key', 'like', "{$prefix}%")->pluck('key')->all();
    }

    public function test_a_completed_goal_without_an_achievement_is_worth_celebrating(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-05 10:00'));
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['title' => 'Run a 10k', 'status' => 'completed']);
        $recorded = Goal::factory()->for($user)->create(['status' => 'completed']);
        Achievement::factory()->for($user)->create(['goal_id' => $recorded->id]);
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user);
        $this->assertSame(["celebrate:goal:{$goal->id}"], $this->insightKeys($user, 'celebrate:'));

        $suggestion = Suggestion::where('key', "celebrate:goal:{$goal->id}")->first();
        $this->actingAs($user)->postJson("/api/v1/suggestions/{$suggestion->id}/accept", ['date' => '2026-11-05'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');
        $achievement = Achievement::where('goal_id', $goal->id)->first();
        $this->assertSame('Run a 10k', $achievement->title);
        $this->assertSame('2026-11-05', $achievement->achieved_on->toDateString());
        $this->assertNotNull($suggestion->fresh()->accepted_at);
    }

    public function test_a_long_streak_offers_its_milestone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-10 12:00'));
        $user = User::factory()->create();
        foreach (range(1, 8) as $day) {
            Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => "2026-11-0{$day} 10:00:00"]);
        }
        Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => '2026-11-09 10:00:00']);

        $this->app->make(SuggestionEngine::class)->refresh($user);

        $this->assertSame(['streak:7:2026-11-01'], $this->insightKeys($user, 'streak:'));
    }

    public function test_a_task_in_progress_with_nothing_logged_for_a_week_is_stalled(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-20 09:00'));
        $user = User::factory()->create();
        $stalled = Task::factory()->for($user)->create(['status' => TaskStatus::InProgress]);
        $worked = Task::factory()->for($user)->create(['status' => TaskStatus::InProgress]);
        $this->travelTo(CarbonImmutable::parse('2026-11-04 09:00'));
        TaskNote::factory()->create(['task_id' => $worked->id, 'user_id' => $user->id, 'logged_on' => '2026-11-03']);
        Task::factory()->for($user)->create(['status' => TaskStatus::InProgress]);

        $this->app->make(SuggestionEngine::class)->refresh($user);

        $this->assertSame(["stalled:{$stalled->id}:2026-W45"], $this->insightKeys($user, 'stalled:'));
        $action = Suggestion::where('key', "stalled:{$stalled->id}:2026-W45")->value('action');
        $this->assertSame(['type' => 'open', 'route' => 'task-detail', 'params' => ['id' => $stalled->id]], $action);
    }

    public function test_an_unreviewed_week_with_work_in_it_asks_for_a_review_early_in_the_next_week(): void
    {
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => '2026-10-28 10:00:00']);
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user, CarbonImmutable::parse('2026-11-02 09:00'));
        $this->assertSame(['review:2026-10-26'], $this->insightKeys($user, 'review:'));

        $user->weeklyReviews()->create(['week_start' => '2026-10-26', 'completed_at' => now()]);
        $engine->refresh($user, CarbonImmutable::parse('2026-11-02 10:00'));
        $this->assertSame([], $this->insightKeys($user, 'review:'));

        $engine->refresh($user, CarbonImmutable::parse('2026-11-12 09:00'));
        $this->assertSame([], $this->insightKeys($user, 'review:'));
    }

    public function test_library_ideas_spread_across_categories_rotate_and_rest_after_dismissal(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $user = User::factory()->create();
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user);
        $first = Suggestion::where('source', 'library')->get();
        $this->assertCount(3, $first);
        $this->assertCount(3, $first->pluck('category')->unique());

        $dismissed = $first->first();
        $dismissed->update(['status' => 'dismissed']);
        $this->assertNotNull($dismissed->fresh()->dismissed_at);

        $this->travelTo(CarbonImmutable::parse('2026-11-06 09:00'));
        $engine->refresh($user);
        $open = Suggestion::where('source', 'library')->where('status', 'new')->get();
        $this->assertCount(3, $open);
        $this->assertEmpty(array_intersect($open->pluck('key')->all(), $first->pluck('key')->all()));
        $this->assertSame(1, Suggestion::where('key', $dismissed->key)->count());

        $this->travelTo(CarbonImmutable::parse('2027-02-01 09:00'));
        config(['suggestions.library' => [collect(config('suggestions.library'))->firstWhere('key', substr($dismissed->key, 5))]]);
        Suggestion::where('status', 'new')->delete();
        $engine->refresh($user);
        $this->assertSame(1, Suggestion::where('key', "{$dismissed->key}:2027-02-01")->count());
    }

    public function test_the_catalogue_lists_every_status_with_their_times(): void
    {
        $user = User::factory()->create(['settings' => ['suggestions' => ['enabled' => false]]]);
        $this->actingAs($user);
        $done = $user->suggestions()->create(['source' => 'custom', 'title' => 'Try pottery', 'category' => 'fun']);
        $user->suggestions()->create(['source' => 'custom', 'title' => 'Call mum', 'category' => 'social']);

        $this->patchJson("/api/v1/suggestions/{$done->id}", ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');
        $this->getJson('/api/v1/suggestions?status=all')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'status', 'created_at', 'updated_at', 'accepted_at', 'done_at', 'dismissed_at']]]);
        $this->getJson('/api/v1/suggestions?status=nope')->assertUnprocessable();

        $this->patchJson("/api/v1/suggestions/{$done->id}", ['status' => 'new'])->assertJsonPath('data.done_at', null);
    }
}
