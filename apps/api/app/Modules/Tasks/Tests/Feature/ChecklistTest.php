<?php

namespace App\Modules\Tasks\Tests\Feature;

use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_items_are_added_ticked_reordered_and_removed(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        $this->actingAs($user);

        $first = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => ' Draft '])->assertCreated()->assertJsonPath('data.title', 'Draft')->json('data.id');
        $second = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'Review'])->assertCreated()->json('data.id');

        $this->patchJson("/api/v1/checklist-items/{$first}", ['done' => true])->assertOk()->assertJsonPath('data.done', true);
        $this->getJson("/api/v1/tasks/{$task->id}")->assertJsonPath('data.checklist', ['done' => 1, 'total' => 2]);
        $this->getJson('/api/v1/tasks')->assertJsonPath('data.0.checklist.total', 2);

        $this->putJson("/api/v1/tasks/{$task->id}/checklist/order", ['ids' => [$second, $first]])
            ->assertOk()->assertJsonPath('data.0.id', $second);
        $this->putJson("/api/v1/tasks/{$task->id}/checklist/order", ['ids' => [$second]])->assertStatus(422);

        $this->deleteJson("/api/v1/checklist-items/{$second}")->assertNoContent();
        $this->getJson("/api/v1/tasks/{$task->id}/checklist")->assertJsonCount(1, 'data');
        $this->assertSame('not_started', $task->fresh()->status->value);
    }

    public function test_ticking_the_last_item_finishes_the_task_when_enabled(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['status' => 'in_progress']);
        $this->actingAs($user);
        $this->patchJson('/api/v1/settings', ['tasks' => ['complete_on_checklist' => true]])->assertOk();

        $a = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'A'])->json('data.id');
        $b = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'B'])->json('data.id');

        $this->patchJson("/api/v1/checklist-items/{$a}", ['done' => true]);
        $this->assertSame('in_progress', $task->fresh()->status->value);
        $this->patchJson("/api/v1/checklist-items/{$b}", ['done' => true]);
        $this->assertSame('completed', $task->fresh()->status->value);
    }

    public function test_a_repeating_task_brings_its_checklist_unticked(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['recurrence' => 'daily', 'due_date' => today()->toDateString()]);
        $this->actingAs($user);
        $item = $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'Stretch'])->json('data.id');
        $this->patchJson("/api/v1/checklist-items/{$item}", ['done' => true]);

        $nextId = $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed'])->assertOk()->json('data.next_occurrence_id');

        $this->getJson("/api/v1/tasks/{$nextId}/checklist")->assertJsonPath('data.0.title', 'Stretch')->assertJsonPath('data.0.done', false);
    }

    public function test_checklists_are_private(): void
    {
        $task = Task::factory()->create();
        $item = $task->checklistItems()->make(['title' => 'Secret']);
        $item->user()->associate($task->user_id)->save();
        $this->actingAs(User::factory()->create());

        $this->getJson("/api/v1/tasks/{$task->id}/checklist")->assertNotFound();
        $this->postJson("/api/v1/tasks/{$task->id}/checklist", ['title' => 'x'])->assertNotFound();
        $this->patchJson("/api/v1/checklist-items/{$item->id}", ['done' => true])->assertNotFound();
        $this->deleteJson("/api/v1/checklist-items/{$item->id}")->assertNotFound();
    }
}
