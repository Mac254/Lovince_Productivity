<?php

namespace App\Modules\Suggestions\Tests\Feature;

use App\Modules\Goals\Models\Goal;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Services\SuggestionEngine;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestionTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_offers_library_ideas_without_repeats(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 09:00'));
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/suggestions')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/v1/suggestions/refresh', ['stale' => true])->assertOk()->assertJsonPath('data.added', 3);
        $this->postJson('/api/v1/suggestions/refresh', ['stale' => true])->assertJsonPath('data.added', 0);

        $this->getJson('/api/v1/suggestions')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.source', 'library')
            ->assertJsonPath('data.0.status', 'new');

        $this->postJson('/api/v1/suggestions/refresh')->assertOk()->assertJsonPath('data.added', 0);
        $first = Suggestion::first();
        $this->patchJson("/api/v1/suggestions/{$first->id}", ['status' => 'dismissed'])->assertOk();
        $this->postJson('/api/v1/suggestions/refresh')->assertJsonPath('data.added', 1);
        $this->assertSame(4, Suggestion::distinct()->count('key'));
    }

    public function test_insights_come_from_the_users_data_and_are_withdrawn_when_resolved(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['settings' => ['planner' => ['plan_ahead' => true]]])->save();
        $now = CarbonImmutable::parse('2026-11-02 19:00');
        $task = Task::factory()->for($user)->create(['due_date' => '2026-10-30']);
        $goal = Goal::factory()->for($user)->create(['title' => 'Get fit', 'created_at' => $now->subWeeks(2)]);
        $schedule = $user->schedules()->create(['name' => 'Workday', 'days' => [1, 2, 3, 4, 5]]);
        $schedule->items()->create(['title' => 'Wake up', 'start_minute' => 300, 'minutes' => 15]);
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user, $now);
        $keys = Suggestion::where('source', 'insight')->pluck('key')->all();
        $this->assertEqualsCanonicalizing(
            ['overdue:2026-11-02', "goal:{$goal->id}:2026-W45", 'plan:2026-11-03', 'journal:2026-11-02'],
            $keys,
        );

        $task->update(['status' => 'completed']);
        TimeBlock::factory()->for($user)->create(['date' => '2026-11-03']);
        $engine->refresh($user, $now);
        $this->assertSame(0, Suggestion::whereIn('key', ['overdue:2026-11-02', 'plan:2026-11-03'])->count());
    }

    public function test_accepting_adds_a_task_or_applies_a_schedule(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-02 19:00'));
        $user = User::factory()->create(['settings' => ['planner' => ['plan_ahead' => true]]]);
        $this->actingAs($user);
        $schedule = $user->schedules()->create(['name' => 'Workday']);
        $schedule->items()->create(['title' => 'Wake up', 'start_minute' => 300, 'minutes' => 15]);
        $idea = $user->suggestions()->create(['source' => 'custom', 'title' => 'Call a friend', 'category' => 'social']);
        $plan = $user->suggestions()->create(['source' => 'insight', 'key' => 'plan:x', 'title' => 'Plan tomorrow', 'category' => 'focus', 'action' => ['type' => 'apply_schedule', 'schedule_id' => $schedule->id, 'date' => '2026-11-03']]);

        $this->postJson("/api/v1/suggestions/{$idea->id}/accept", ['date' => '2026-11-03'])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.task.title', 'Call a friend');
        $this->assertSame('2026-11-03', Task::first()->due_date->toDateString());

        $this->postJson("/api/v1/suggestions/{$plan->id}/accept")->assertOk();
        $this->assertSame(1, TimeBlock::whereDate('date', '2026-11-03')->count());
    }

    public function test_accepting_twice_acts_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $idea = $user->suggestions()->create(['source' => 'custom', 'title' => 'Call a friend', 'category' => 'social', 'action' => ['type' => 'achievement']]);

        $this->postJson("/api/v1/suggestions/{$idea->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');
        $this->postJson("/api/v1/suggestions/{$idea->id}/accept")->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertSame(1, $user->achievements()->count());
    }

    public function test_a_failed_accept_leaves_nothing_behind(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $plan = $user->suggestions()->create(['source' => 'custom', 'title' => 'Plan', 'category' => 'focus', 'action' => ['type' => 'apply_schedule', 'schedule_id' => 999]]);

        $this->postJson("/api/v1/suggestions/{$plan->id}/accept")->assertUnprocessable();

        $this->assertSame('new', $plan->fresh()->status->value);
    }

    public function test_own_suggestions_are_managed_and_private(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/suggestions', ['title' => 'Try pottery', 'category' => 'fun'])
            ->assertCreated()
            ->assertJsonPath('data.source', 'custom')
            ->json('data.id');
        $this->postJson('/api/v1/suggestions', ['title' => 'x', 'category' => 'nope'])->assertUnprocessable()->assertJsonValidationErrors('category');
        $this->patchJson("/api/v1/suggestions/{$id}", ['status' => 'done'])->assertOk()->assertJsonPath('data.status', 'done');
        $this->getJson('/api/v1/suggestions?status=done')->assertJsonCount(1, 'data');

        $this->actingAs(User::factory()->create());
        $this->patchJson("/api/v1/suggestions/{$id}", ['status' => 'new'])->assertNotFound();
        $this->postJson("/api/v1/suggestions/{$id}/accept")->assertNotFound();
        $this->deleteJson("/api/v1/suggestions/{$id}")->assertNotFound();
    }

    public function test_suggestions_can_be_switched_off(): void
    {
        $user = User::factory()->create(['settings' => ['suggestions' => ['enabled' => false]]]);
        $this->actingAs($user);

        $this->postJson('/api/v1/suggestions/refresh', ['stale' => true])->assertOk()->assertJsonPath('data.added', 0);
        $this->getJson('/api/v1/suggestions')->assertOk()->assertJsonCount(0, 'data');
    }
}
