<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
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

    public function test_task_list_is_paginated(): void
    {
        Task::factory()->for($this->user)->count(30)->create();

        $this->getJson('/api/v1/tasks?per_page=10')
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.total', 30);
    }
}
