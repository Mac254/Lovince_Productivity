<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_user_can_create_weekly_and_long_term_goals(): void
    {
        $this->postJson('/api/v1/goals', ['title' => 'Ship V2', 'timeframe' => 'weekly'])
            ->assertCreated()
            ->assertJsonPath('data.timeframe', 'weekly')
            ->assertJsonPath('data.progress', 0)
            ->assertJsonPath('data.tasks_count', 0);

        $this->postJson('/api/v1/goals', ['title' => 'Learn Rust', 'timeframe' => 'long_term'])->assertCreated();

        $this->assertSame(2, $this->user->goals()->count());
    }

    public function test_goal_validation(): void
    {
        $this->postJson('/api/v1/goals', [
            'title' => '',
            'status' => 'paused-ish',
            'timeframe' => 'monthly',
            'start_date' => '2026-06-10',
            'target_date' => '2026-06-01',
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'status', 'timeframe', 'target_date']);
    }

    public function test_progress_counts_completed_tasks_and_ignores_cancelled_ones(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->for($goal)->completed()->count(3)->create();
        Task::factory()->for($this->user)->for($goal)->create();
        Task::factory()->for($this->user)->for($goal)->create(['status' => 'cancelled']);

        $this->getJson("/api/v1/goals/{$goal->id}")
            ->assertJsonPath('data.progress', 75)
            ->assertJsonPath('data.tasks_count', 4)
            ->assertJsonPath('data.completed_tasks_count', 3);
    }

    public function test_goal_with_only_cancelled_tasks_reports_zero_progress(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        Task::factory()->for($this->user)->for($goal)->create(['status' => 'cancelled']);

        $this->getJson("/api/v1/goals/{$goal->id}")->assertJsonPath('data.progress', 0);
    }

    public function test_deleting_a_goal_keeps_its_tasks(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        $task = Task::factory()->for($this->user)->for($goal)->create();

        $this->deleteJson("/api/v1/goals/{$goal->id}")->assertNoContent();

        $this->assertModelMissing($goal);
        $this->assertNull($task->fresh()->goal_id);
    }

    public function test_other_users_goals_are_invisible(): void
    {
        $foreign = Goal::factory()->create();

        $this->getJson("/api/v1/goals/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/goals/{$foreign->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/v1/goals/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/goals')->assertJsonCount(0, 'data');
    }
}
