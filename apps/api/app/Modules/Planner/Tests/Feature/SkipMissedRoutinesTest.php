<?php

namespace App\Modules\Planner\Tests\Feature;

use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkipMissedRoutinesTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_routine_tasks_from_past_days_are_skipped(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->travelTo(now()->subDay());
        $id = $this->postJson('/api/v1/schedules', ['name' => 'Day', 'items' => [
            ['title' => 'Exercise', 'start_minute' => 315, 'minutes' => 45],
            ['title' => 'Read', 'start_minute' => 375, 'minutes' => 20],
        ]])->assertCreated()->json('data.id');
        $yesterday = today()->toDateString();
        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => $yesterday])->assertSuccessful();
        Task::query()->where('title', 'Read')->first()->update(['status' => 'completed']);
        $project = $user->tasks()->create(['title' => 'Ship the release', 'due_date' => $yesterday]);
        $this->travelBack();

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => today()->toDateString()])->assertSuccessful();

        $this->artisan('planner:skip-missed-routines')->assertSuccessful();

        $missed = Task::query()->where('title', 'Exercise')->whereDate('due_date', $yesterday)->first();
        $this->assertSame('cancelled', $missed->status->value);
        $this->assertSame('Not done on the day', $missed->cancel_reason);
        $this->assertSame('completed', Task::query()->where('title', 'Read')->whereDate('due_date', $yesterday)->first()->status->value);
        $this->assertSame('not_started', Task::query()->where('title', 'Exercise')->whereDate('due_date', today())->first()->status->value);
        $this->assertSame('not_started', $project->fresh()->status->value);
    }

    public function test_routine_tasks_stay_open_when_skipping_is_off(): void
    {
        $user = User::factory()->create(['settings' => ['schedules' => ['skip_missed' => false]]]);
        $this->actingAs($user);

        $this->travelTo(now()->subDay());
        $id = $this->postJson('/api/v1/schedules', ['name' => 'Day', 'items' => [
            ['title' => 'Exercise', 'start_minute' => 315, 'minutes' => 45],
        ]])->assertCreated()->json('data.id');
        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => today()->toDateString()])->assertSuccessful();
        $this->travelBack();

        $this->artisan('planner:skip-missed-routines')->assertSuccessful();

        $this->assertSame('not_started', Task::query()->where('title', 'Exercise')->first()->status->value);
    }
}
