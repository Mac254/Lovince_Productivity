<?php

namespace App\Modules\Tasks\Tests\Feature;

use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringTaskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-04 10:00:00');
    }

    public function test_completing_a_repeating_task_schedules_the_next_one_once(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/tasks', [
            'title' => 'Weekly report',
            'recurrence' => 'weekly',
            'start_date' => '2026-11-02',
            'due_date' => '2026-11-06',
            'estimate_minutes' => 45,
        ])->assertCreated()->assertJsonPath('data.recurrence', 'weekly')->json('data.id');

        $nextId = $this->patchJson("/api/v1/tasks/{$id}", ['status' => 'completed'])->assertOk()->json('data.next_occurrence_id');

        $next = Task::findOrFail($nextId);
        $this->assertSame('Weekly report', $next->title);
        $this->assertSame('2026-11-13', $next->due_date->toDateString());
        $this->assertSame('2026-11-09', $next->start_date->toDateString());
        $this->assertSame(45, $next->estimate_minutes);
        $this->assertSame('not_started', $next->status->value);
        $this->assertNull($next->next_occurrence_id);

        $this->patchJson("/api/v1/tasks/{$id}", ['status' => 'in_progress'])->assertOk();
        $this->patchJson("/api/v1/tasks/{$id}", ['status' => 'completed'])->assertOk();
        $this->assertSame(2, Task::count());
    }

    public function test_overdue_repeats_skip_to_the_next_future_date(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['recurrence' => 'daily', 'due_date' => '2026-10-30']);
        $this->actingAs($user);

        $nextId = $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->json('data.next_occurrence_id');
        $this->assertSame('2026-11-05', Task::findOrFail($nextId)->due_date->toDateString());
    }

    public function test_weekdays_skip_the_weekend_and_repeat_from_completion_when_set(): void
    {
        Carbon::setTestNow('2026-11-06 10:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->patchJson('/api/v1/settings', ['tasks' => ['repeat_from' => 'completed']])->assertOk();
        $task = Task::factory()->for($user)->create(['recurrence' => 'weekdays', 'due_date' => '2026-11-20']);

        $nextId = $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->json('data.next_occurrence_id');
        $this->assertSame('2026-11-09', Task::findOrFail($nextId)->due_date->toDateString());
    }

    public function test_tasks_without_a_repeat_do_not_repeat(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['due_date' => '2026-11-04']);
        $this->actingAs($user);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertJsonPath('data.next_occurrence_id', null);
        $this->postJson('/api/v1/tasks', ['title' => 'x', 'recurrence' => 'yearly'])->assertUnprocessable()->assertJsonValidationErrors('recurrence');
        $this->assertSame(1, Task::count());
    }
}
