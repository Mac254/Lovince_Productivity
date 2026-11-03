<?php

namespace App\Modules\Planner\Tests\Feature;

use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimeBlockTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocks_are_created_listed_moved_and_deleted(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Write the spec']);
        $this->actingAs($user);
        $today = today()->toDateString();

        $id = $this->postJson('/api/v1/time-blocks', ['task_id' => $task->id, 'date' => $today, 'start_minute' => 540, 'minutes' => 90])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Write the spec')
            ->assertJsonPath('data.task.id', $task->id)
            ->json('data.id');
        $this->postJson('/api/v1/time-blocks', ['title' => 'Lunch', 'date' => $today, 'start_minute' => 720, 'minutes' => 60])->assertCreated();

        $this->getJson("/api/v1/time-blocks?date={$today}")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.1.title', 'Lunch');

        $this->patchJson("/api/v1/time-blocks/{$id}", ['start_minute' => 600])->assertOk()->assertJsonPath('data.start_minute', 600);
        $this->deleteJson("/api/v1/time-blocks/{$id}")->assertNoContent();
        $this->assertSame(1, TimeBlock::count());
    }

    public function test_blocks_may_not_overlap_or_run_past_midnight(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $today = today()->toDateString();
        $block = TimeBlock::factory()->for($user)->create(['start_minute' => 540, 'minutes' => 60]);

        $this->postJson('/api/v1/time-blocks', ['title' => 'Clash', 'date' => $today, 'start_minute' => 570, 'minutes' => 60])
            ->assertUnprocessable()->assertJsonValidationErrors('start_minute');
        $this->postJson('/api/v1/time-blocks', ['title' => 'Late', 'date' => $today, 'start_minute' => 1410, 'minutes' => 60])
            ->assertUnprocessable()->assertJsonValidationErrors('minutes');
        $this->postJson('/api/v1/time-blocks', ['date' => $today, 'start_minute' => 600, 'minutes' => 30])
            ->assertUnprocessable()->assertJsonValidationErrors('title');
        $this->postJson('/api/v1/time-blocks', ['title' => 'Next', 'date' => $today, 'start_minute' => 600, 'minutes' => 30])->assertCreated();
        $this->patchJson("/api/v1/time-blocks/{$block->id}", ['minutes' => 30])->assertOk();

        $missed = TimeBlock::factory()->for($user)->create(['date' => today()->subDay()->toDateString(), 'start_minute' => 600, 'minutes' => 30]);
        $this->patchJson("/api/v1/time-blocks/{$missed->id}", ['date' => $today])
            ->assertUnprocessable()->assertJsonValidationErrors('start_minute');
        $this->patchJson("/api/v1/time-blocks/{$missed->id}", ['date' => $today, 'start_minute' => 660])->assertOk();
    }

    public function test_blocks_are_private(): void
    {
        $block = TimeBlock::factory()->create();
        $other = Task::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/time-blocks')->assertJsonCount(0, 'data');
        $this->patchJson("/api/v1/time-blocks/{$block->id}", ['minutes' => 30])->assertNotFound();
        $this->deleteJson("/api/v1/time-blocks/{$block->id}")->assertNotFound();
        $this->postJson('/api/v1/time-blocks', ['task_id' => $other->id, 'date' => today()->toDateString(), 'start_minute' => 0, 'minutes' => 30])
            ->assertUnprocessable()->assertJsonValidationErrors('task_id');
    }

    public function test_deleting_a_task_keeps_its_block(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Gone']);
        $this->actingAs($user);
        $this->postJson('/api/v1/time-blocks', ['task_id' => $task->id, 'date' => today()->toDateString(), 'start_minute' => 0, 'minutes' => 30])->assertCreated();

        $this->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();
        $this->getJson('/api/v1/time-blocks')->assertJsonPath('data.0.task', null)->assertJsonPath('data.0.title', 'Gone');
    }
}
