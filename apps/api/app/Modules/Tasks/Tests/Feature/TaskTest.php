<?php

namespace App\Modules\Tasks\Tests\Feature;

use App\Modules\Goals\Models\Goal;
use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_user_can_create_a_task(): void
    {
        $goal = Goal::factory()->for($this->user)->create();

        $this->postJson('/api/v1/tasks', [
            'title' => '  Ship the client portal  ',
            'priority' => 'high',
            'category' => 'development',
            'due_date' => today()->toDateString(),
            'goal_id' => $goal->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Ship the client portal')
            ->assertJsonPath('data.status', 'not_started')
            ->assertJsonPath('data.goal.id', $goal->id);

        $this->assertDatabaseHas('tasks', ['user_id' => $this->user->id, 'title' => 'Ship the client portal']);
    }

    public function test_user_id_from_the_request_is_ignored(): void
    {
        $other = User::factory()->create();

        $this->postJson('/api/v1/tasks', ['title' => 'Mine', 'user_id' => $other->id])->assertCreated();

        $this->assertSame($this->user->id, Task::firstOrFail()->user_id);
    }

    public function test_validation_rejects_bad_input(): void
    {
        $this->postJson('/api/v1/tasks', [
            'title' => '',
            'status' => 'done',
            'priority' => 'critical',
            'due_date' => '2026-02-30',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'status', 'priority', 'due_date']);
    }

    public function test_due_date_cannot_precede_start_date(): void
    {
        $this->postJson('/api/v1/tasks', [
            'title' => 'Backwards',
            'start_date' => '2026-05-10',
            'due_date' => '2026-05-01',
        ])->assertUnprocessable()->assertJsonValidationErrors('due_date');

        $task = Task::factory()->for($this->user)->create(['start_date' => '2026-05-10']);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['due_date' => '2026-05-01'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('due_date');
    }

    public function test_cannot_attach_task_to_another_users_goal(): void
    {
        $foreignGoal = Goal::factory()->create();

        $this->postJson('/api/v1/tasks', ['title' => 'Sneaky', 'goal_id' => $foreignGoal->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('goal_id');
    }

    public function test_completing_a_task_records_completion_time_and_reopening_clears_it(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($task->fresh()->completed_at);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'in_progress'])->assertOk();
        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_user_can_delete_a_task(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($task);
    }

    public function test_other_users_tasks_are_invisible(): void
    {
        $foreign = Task::factory()->create();

        $this->getJson("/api/v1/tasks/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/tasks/{$foreign->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/tasks/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/tasks')->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('tasks', ['id' => $foreign->id, 'title' => $foreign->title]);
    }

    public function test_tasks_can_be_searched_and_filtered(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->create(['title' => 'Write blog post', 'priority' => 'high', 'goal_id' => $goal->id]);
        Task::factory()->for($this->user)->create(['title' => 'Refactor', 'description' => 'Clean the blog module', 'priority' => 'low']);
        Task::factory()->for($this->user)->create(['title' => 'Overdue thing', 'due_date' => today()->subDays(2)->toDateString()]);
        Task::factory()->for($this->user)->completed()->create(['title' => 'Old done', 'due_date' => today()->subDays(3)->toDateString()]);

        $this->getJson('/api/v1/tasks?search=blog')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/tasks?priority=high')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/tasks?goal_id={$goal->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?due=overdue')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Overdue thing')
            ->assertJsonPath('data.0.is_overdue', true);
        $this->getJson('/api/v1/tasks?status=completed')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?status=bogus')->assertUnprocessable();
    }

    public function test_tomorrows_planned_tasks_stay_hidden_until_their_day(): void
    {
        $tomorrow = today()->addDay()->toDateString();
        $planned = Task::factory()->for($this->user)->create(['title' => 'Exercise', 'due_date' => $tomorrow]);
        TimeBlock::factory()->for($this->user)->create(['task_id' => $planned->id, 'date' => $tomorrow]);
        Task::factory()->for($this->user)->create(['title' => 'Send invoice', 'due_date' => $tomorrow]);
        $today = Task::factory()->for($this->user)->create(['title' => 'Stand-up', 'due_date' => today()->toDateString()]);
        TimeBlock::factory()->for($this->user)->create(['task_id' => $today->id]);
        Task::factory()->for($this->user)->create(['title' => 'Someday']);

        $this->getJson('/api/v1/tasks?hide_planned_ahead=true&sort=title')
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.*.title', ['Send invoice', 'Someday', 'Stand-up']);
        $this->getJson('/api/v1/tasks')->assertJsonCount(4, 'data');

        $this->travelTo(today()->addDay());
        $this->getJson('/api/v1/tasks?hide_planned_ahead=true')->assertJsonCount(4, 'data');
    }

    public function test_task_list_is_paginated(): void
    {
        Task::factory()->for($this->user)->count(30)->create();

        $this->getJson('/api/v1/tasks?per_page=10')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30);
    }

    public function test_estimates_are_saved_and_compared_with_time_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/tasks', ['title' => 'Draft the plan', 'estimate_minutes' => 120])
            ->assertCreated()
            ->assertJsonPath('data.estimate_minutes', 120)
            ->assertJsonPath('data.logged_minutes', 0)
            ->json('data.id');

        $this->postJson("/api/v1/tasks/{$id}/notes", ['body' => 'Outline', 'minutes' => 90])->assertCreated();
        $this->getJson("/api/v1/tasks/{$id}")->assertJsonPath('data.logged_minutes', 90);
        $this->getJson('/api/v1/tasks')->assertJsonPath('data.0.logged_minutes', 90);

        $this->patchJson("/api/v1/tasks/{$id}", ['status' => 'completed'])->assertOk();
        $this->getJson('/api/v1/metrics?days=7')
            ->assertJsonPath('data.totals.estimated_tasks', 1)
            ->assertJsonPath('data.totals.estimate_accuracy', 75);

        $this->patchJson("/api/v1/tasks/{$id}", ['estimate_minutes' => 0])->assertJsonValidationErrors('estimate_minutes');
    }
}
