<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Task;
use App\Models\TaskType;
use App\Models\User;
use App\Support\Contract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_creates_lists_renames_and_deletes_their_types(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/task-types', ['name' => '  Study   time ', 'tone' => 'violet'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Study time')
            ->assertJsonPath('data.tone', 'violet')
            ->json('data.id');

        $this->postJson('/api/v1/task-types', ['name' => 'Study time'])->assertUnprocessable()->assertJsonValidationErrors('name');

        Task::factory()->for($user)->create(['task_type_id' => $id]);
        $this->getJson('/api/v1/task-types')->assertOk()->assertJsonPath('data.0.tasks_count', 1);

        $this->patchJson("/api/v1/task-types/{$id}", ['name' => 'Study'])->assertOk()->assertJsonPath('data.name', 'Study');

        $this->deleteJson("/api/v1/task-types/{$id}")->assertNoContent();
        $this->assertDatabaseCount('tasks', 1);
        $this->assertNull(Task::first()->task_type_id);
    }

    public function test_tasks_are_created_with_a_type_and_a_goal_and_can_be_filtered_by_type(): void
    {
        $user = User::factory()->create();
        $type = TaskType::factory()->for($user)->create(['name' => 'Personal', 'tone' => 'rose']);
        $goal = Goal::factory()->for($user)->create();
        Task::factory()->for($user)->create();

        $this->actingAs($user)->postJson('/api/v1/tasks', [
            'title' => 'Teach graphics',
            'task_type_id' => $type->id,
            'goal_id' => $goal->id,
        ])
            ->assertCreated()
            ->assertJsonPath('data.type.name', 'Personal')
            ->assertJsonPath('data.type.tone', 'rose')
            ->assertJsonPath('data.goal.id', $goal->id);

        $this->getJson("/api/v1/tasks?task_type_id={$type->id}")->assertJsonCount(1, 'data');
    }

    public function test_types_are_private_and_capped(): void
    {
        [$owner, $other] = User::factory()->count(2)->create();
        $type = TaskType::factory()->for($owner)->create();

        $this->actingAs($other);
        $this->getJson('/api/v1/task-types')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/task-types/{$type->id}", ['name' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/task-types/{$type->id}")->assertNotFound();
        $this->postJson('/api/v1/tasks', ['title' => 'X', 'task_type_id' => $type->id])->assertJsonValidationErrors('task_type_id');

        TaskType::factory()->for($other)->count(Contract::TASK_TYPE_LIMIT)->create();
        $this->postJson('/api/v1/task-types', ['name' => 'One more'])->assertJsonValidationErrors('name');
    }

    public function test_a_task_has_either_a_custom_type_or_a_built_in_category(): void
    {
        $user = User::factory()->create();
        $type = TaskType::factory()->for($user)->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/tasks', ['title' => 'Mixed', 'category' => 'integration', 'task_type_id' => $type->id])
            ->assertCreated()
            ->assertJsonPath('data.category', 'general')
            ->assertJsonPath('data.task_type_id', $type->id)
            ->json('data.id');

        $this->patchJson("/api/v1/tasks/{$id}", ['category' => 'development'])
            ->assertJsonPath('data.category', 'development')
            ->assertJsonPath('data.task_type_id', null);

        $this->patchJson("/api/v1/tasks/{$id}", ['task_type_id' => $type->id])
            ->assertJsonPath('data.category', 'general')
            ->assertJsonPath('data.type.id', $type->id);
    }
}
