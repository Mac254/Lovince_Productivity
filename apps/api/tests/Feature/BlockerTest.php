<?php

namespace Tests\Feature;

use App\Models\Blocker;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_blocker_can_be_logged_against_a_task_and_resolved(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $id = $this->postJson('/api/v1/blockers', ['description' => '  Waiting on API keys  ', 'task_id' => $task->id])
            ->assertCreated()
            ->assertJsonPath('data.description', 'Waiting on API keys')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.task.id', $task->id)
            ->json('data.id');

        $this->patchJson("/api/v1/blockers/{$id}", ['status' => 'resolved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');
        $this->assertNotNull(Blocker::find($id)->resolved_at);

        $this->patchJson("/api/v1/blockers/{$id}", ['status' => 'open']);
        $this->assertNull(Blocker::find($id)->resolved_at);
    }

    public function test_blockers_are_filtered_and_scoped(): void
    {
        Blocker::factory()->for($this->user)->create();
        Blocker::factory()->for($this->user)->resolved()->create();
        Blocker::factory()->create();

        $this->getJson('/api/v1/blockers')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/blockers?status=open')->assertJsonCount(1, 'data');
    }

    public function test_cannot_touch_or_attach_to_other_users_records(): void
    {
        $foreign = Blocker::factory()->create();
        $foreignTask = Task::factory()->create();

        $this->patchJson("/api/v1/blockers/{$foreign->id}", ['status' => 'resolved'])->assertNotFound();
        $this->deleteJson("/api/v1/blockers/{$foreign->id}")->assertNotFound();
        $this->postJson('/api/v1/blockers', ['description' => 'x', 'task_id' => $foreignTask->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('task_id');
    }

    public function test_deleting_a_task_removes_its_blockers(): void
    {
        $task = Task::factory()->for($this->user)->create();
        $blocker = Blocker::factory()->for($this->user)->create(['task_id' => $task->id]);

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();

        $this->assertModelMissing($blocker);
    }
}
