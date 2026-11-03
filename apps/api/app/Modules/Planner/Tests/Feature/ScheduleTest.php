<?php

namespace App\Modules\Planner\Tests\Feature;

use App\Modules\Planner\Models\Schedule;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Suggestions\Models\Suggestion;
use App\Modules\Suggestions\Services\SuggestionEngine;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    private function routine(): array
    {
        return [
            'name' => 'Weekday',
            'days' => [1, 2, 3, 4, 5],
            'items' => [
                ['title' => 'Exercise', 'start_minute' => 315, 'minutes' => 45],
                ['title' => 'Wake up', 'start_minute' => 300, 'minutes' => 15],
            ],
        ];
    }

    public function test_schedules_are_created_listed_and_updated(): void
    {
        $this->actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/schedules', $this->routine())
            ->assertCreated()
            ->assertJsonPath('data.items.0.title', 'Wake up')
            ->assertJsonPath('data.days', [1, 2, 3, 4, 5])
            ->json('data.id');

        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(1, 'data')->assertJsonCount(2, 'data.0.items');

        $this->patchJson("/api/v1/schedules/{$id}", ['items' => [['title' => 'Read', 'start_minute' => 375, 'minutes' => 45]]])
            ->assertOk()
            ->assertJsonPath('data.name', 'Weekday')
            ->assertJsonCount(1, 'data.items');

        $this->deleteJson("/api/v1/schedules/{$id}")->assertNoContent();
        $this->assertSame(0, Schedule::count());
    }

    public function test_items_may_not_overlap_or_run_past_midnight(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/v1/schedules', ['name' => 'Clash', 'items' => [
            ['title' => 'A', 'start_minute' => 540, 'minutes' => 60],
            ['title' => 'B', 'start_minute' => 570, 'minutes' => 30],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items');

        $this->postJson('/api/v1/schedules', ['name' => 'Late', 'items' => [
            ['title' => 'A', 'start_minute' => 1410, 'minutes' => 60],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items');
    }

    public function test_applying_creates_tasks_and_blocks_once(): void
    {
        $user = User::factory()->create(['settings' => ['planner' => ['plan_ahead' => true]]]);
        $this->actingAs($user);
        $date = today()->addDay()->toDateString();
        Task::factory()->for($user)->create(['title' => 'Exercise', 'due_date' => $date]);
        $id = $this->postJson('/api/v1/schedules', $this->routine())->json('data.id');

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $date])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'Wake up');
        $this->assertSame(2, Task::count());

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $date])
            ->assertUnprocessable()->assertJsonValidationErrors('date');

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $date, 'replace' => true])->assertOk();
        $this->assertSame(2, TimeBlock::count());
        $this->assertSame(2, Task::count());
    }

    public function test_schedules_are_listed_in_week_order(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ([['Sunday', [7]], ['Friday', [5]], ['Every day', []], ['Monday', [1]], ['Weekdays', [1, 2, 3, 4, 5]], ['Weekends', [6, 7]], ['Tuesday', [2]]] as [$name, $days]) {
            $user->schedules()->create(['name' => $name, 'days' => $days]);
        }

        $this->getJson('/api/v1/schedules')
            ->assertJsonPath('data.*.name', ['Every day', 'Monday', 'Weekdays', 'Tuesday', 'Friday', 'Weekends', 'Sunday']);
    }

    public function test_schedules_only_fill_later_days_when_planning_ahead_is_on(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/schedules', $this->routine())->json('data.id');
        $tomorrow = today()->addDay()->toDateString();

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $tomorrow])
            ->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertSame(0, TimeBlock::count());
        $this->assertSame(0, Task::count());

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => today()->toDateString()])->assertOk();

        $this->patchJson('/api/v1/settings', ['planner' => ['plan_ahead' => true]])->assertOk();
        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $tomorrow])->assertOk();
        $this->assertSame(2, TimeBlock::whereDate('date', $tomorrow)->count());
    }

    public function test_tomorrow_is_only_suggested_when_planning_ahead_is_on(): void
    {
        $user = User::factory()->create();
        $now = CarbonImmutable::parse('2026-11-02 19:00');
        $user->schedules()->create(['name' => 'Every day'])->items()->create(['title' => 'Wake up', 'start_minute' => 300, 'minutes' => 15]);
        $tuesday = $user->schedules()->create(['name' => 'Tuesday', 'days' => [2]]);
        $tuesday->items()->create(['title' => 'Wake up', 'start_minute' => 300, 'minutes' => 15]);
        $engine = $this->app->make(SuggestionEngine::class);

        $engine->refresh($user, $now);
        $this->assertSame(0, Suggestion::where('key', 'plan:2026-11-03')->count());

        $user->forceFill(['settings' => ['planner' => ['plan_ahead' => true]]])->save();
        $engine->refresh($user->fresh(), $now);
        $this->assertSame(['type' => 'apply_schedule', 'schedule_id' => $tuesday->id, 'date' => '2026-11-03'], Suggestion::where('key', 'plan:2026-11-03')->first()?->action);
    }

    public function test_schedules_are_private(): void
    {
        $owner = User::factory()->create();
        $schedule = $owner->schedules()->create(['name' => 'Mine']);
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/v1/schedules/{$schedule->id}")->assertNotFound();
        $this->patchJson("/api/v1/schedules/{$schedule->id}", ['name' => 'Theirs'])->assertNotFound();
        $this->postJson("/api/v1/schedules/{$schedule->id}/apply", ['date' => today()->toDateString()])->assertNotFound();
        $this->deleteJson("/api/v1/schedules/{$schedule->id}")->assertNotFound();
        $this->getJson('/api/v1/schedules')->assertOk()->assertJsonCount(0, 'data');
    }
}
