<?php

namespace App\Modules\Achievements\Tests\Feature;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AchievementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_user_can_record_an_achievement_for_a_goal_and_task(): void
    {
        $goal = Goal::factory()->for($this->user)->create(['title' => 'Read 12 books']);
        $task = Task::factory()->for($this->user)->for($goal)->create(['title' => 'Finish Dune']);

        $this->postJson('/api/v1/achievements', [
            'title' => '  Finished Dune  ',
            'achieved_on' => '2026-11-02',
            'category' => 'growth',
            'goal_id' => $goal->id,
            'task_id' => $task->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Finished Dune')
            ->assertJsonPath('data.achieved_on', '2026-11-02')
            ->assertJsonPath('data.category', 'growth')
            ->assertJsonPath('data.goal.title', 'Read 12 books')
            ->assertJsonPath('data.task.title', 'Finish Dune');

        $this->assertSame(1, $this->user->achievements()->count());
    }

    public function test_achievement_validation(): void
    {
        $foreignGoal = Goal::factory()->create();
        $foreignTask = Task::factory()->create();

        $this->postJson('/api/v1/achievements', [
            'title' => '',
            'achieved_on' => '02/11/2026',
            'category' => 'luck',
            'goal_id' => $foreignGoal->id,
            'task_id' => $foreignTask->id,
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'achieved_on', 'category', 'goal_id', 'task_id']);
    }

    public function test_achievements_are_listed_newest_first_and_filtered(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        Achievement::factory()->for($this->user)->create(['title' => 'Old', 'achieved_on' => '2025-12-31']);
        Achievement::factory()->for($this->user)->create(['title' => 'New', 'achieved_on' => '2026-10-01', 'goal_id' => $goal->id]);
        Achievement::factory()->for($this->user)->create(['title' => 'Middle', 'achieved_on' => '2026-03-01', 'category' => 'health']);

        $this->getJson('/api/v1/achievements')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'New')
            ->assertJsonPath('data.2.title', 'Old');

        $this->getJson("/api/v1/achievements?goal_id={$goal->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'New');
        $this->getJson('/api/v1/achievements?category=health')->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Middle');
        $this->getJson('/api/v1/achievements?year=2026')->assertJsonCount(2, 'data');
    }

    public function test_user_can_update_and_delete_an_achievement(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        $achievement = Achievement::factory()->for($this->user)->create(['goal_id' => $goal->id]);

        $this->patchJson("/api/v1/achievements/{$achievement->id}", ['title' => 'Ran a marathon', 'goal_id' => null, 'category' => 'health'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Ran a marathon')
            ->assertJsonPath('data.goal_id', null)
            ->assertJsonPath('data.category', 'health');

        $this->deleteJson("/api/v1/achievements/{$achievement->id}")->assertNoContent();
        $this->assertModelMissing($achievement);
    }

    public function test_deleting_a_goal_keeps_its_achievements(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        $achievement = Achievement::factory()->for($this->user)->create(['goal_id' => $goal->id]);

        $this->deleteJson("/api/v1/goals/{$goal->id}")->assertNoContent();

        $this->assertNull($achievement->fresh()->goal_id);
    }

    public function test_other_users_achievements_are_invisible(): void
    {
        $foreign = Achievement::factory()->create();

        $this->patchJson("/api/v1/achievements/{$foreign->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->deleteJson("/api/v1/achievements/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/achievements')->assertJsonCount(0, 'data');
    }
}
