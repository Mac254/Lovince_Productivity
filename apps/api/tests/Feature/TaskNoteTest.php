<?php

namespace Tests\Feature;

use App\Models\Task;
use App\Models\TaskNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TaskNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_is_logged_listed_edited_and_removed_on_a_task(): void
    {
        Carbon::setTestNow('2026-10-28 15:00:00');
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        $this->actingAs($user);

        $id = $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => '  Read chapter 3  ', 'minutes' => 45])
            ->assertCreated()
            ->assertJsonPath('data.body', 'Read chapter 3')
            ->assertJsonPath('data.logged_on', '2026-10-28')
            ->assertJsonPath('data.minutes', 45)
            ->json('data.id');
        $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => 'Outlined notes', 'logged_on' => '2026-10-27']);

        $this->getJson("/api/v1/tasks/{$task->id}/notes")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.logged_on', '2026-10-28');

        $this->patchJson("/api/v1/task-notes/{$id}", ['body' => 'Read chapters 3 and 4', 'minutes' => null])
            ->assertOk()
            ->assertJsonPath('data.minutes', null);

        $this->deleteJson("/api/v1/task-notes/{$id}")->assertNoContent();
        $this->assertSame(1, TaskNote::count());
    }

    public function test_notes_are_validated_private_and_removed_with_their_task(): void
    {
        Carbon::setTestNow('2026-10-28 15:00:00');
        [$owner, $other] = User::factory()->count(2)->create();
        $task = Task::factory()->for($owner)->create();
        $note = TaskNote::factory()->create(['task_id' => $task->id]);

        $this->actingAs($owner);
        $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => ''])->assertJsonValidationErrors('body');
        $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => 'x', 'logged_on' => '2026-10-29'])->assertJsonValidationErrors('logged_on');
        $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => 'x', 'minutes' => 721])->assertJsonValidationErrors('minutes');

        $this->actingAs($other);
        $this->getJson("/api/v1/tasks/{$task->id}/notes")->assertNotFound();
        $this->postJson("/api/v1/tasks/{$task->id}/notes", ['body' => 'Sneaky'])->assertNotFound();
        $this->patchJson("/api/v1/task-notes/{$note->id}", ['body' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/task-notes/{$note->id}")->assertNotFound();

        $this->actingAs($owner)->deleteJson("/api/v1/tasks/{$task->id}")->assertNoContent();
        $this->assertSame(0, TaskNote::count());
    }

    public function test_the_days_work_log_goes_into_its_report(): void
    {
        Carbon::setTestNow('2026-10-28 18:00:00');
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Read the book']);
        TaskNote::factory()->create(['task_id' => $task->id, 'body' => 'Chapter 3', 'minutes' => 50]);
        TaskNote::factory()->create(['task_id' => $task->id, 'body' => 'Chapter 4', 'minutes' => 25]);
        TaskNote::factory()->create(['task_id' => $task->id, 'body' => 'Yesterday', 'logged_on' => '2026-10-27']);

        $report = $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated();

        $report->assertJsonPath('data.summary.notes_logged', 2)
            ->assertJsonPath('data.summary.minutes_logged', 75)
            ->assertJsonCount(2, 'data.work_log')
            ->assertJsonPath('data.work_log.0.task.title', 'Read the book');
        $this->assertStringContainsString('2 work notes were logged (1h 15m).', $report->json('data.narrative'));
    }

    public function test_saved_reports_follow_a_deleted_task_and_late_work_on_the_plan(): void
    {
        Carbon::setTestNow('2026-10-27 23:55:00');
        $user = User::factory()->create();
        $planned = Task::factory()->for($user)->create(['due_date' => '2026-10-27', 'created_at' => '2026-10-27 08:00:00']);
        $other = Task::factory()->for($user)->create(['created_at' => '2026-10-27 08:00:00']);
        TaskNote::factory()->create(['task_id' => $other->id, 'body' => 'Spike', 'logged_on' => '2026-10-27']);
        $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated()
            ->assertJsonPath('data.summary.planned_worked', 0)
            ->assertJsonPath('data.summary.notes_logged', 1);

        Carbon::setTestNow('2026-10-28 10:00:00');
        $this->postJson("/api/v1/tasks/{$planned->id}/notes", ['body' => 'Forgot to log this', 'logged_on' => '2026-10-27'])
            ->assertCreated();
        $this->getJson('/api/v1/reports/2026-10-27')
            ->assertJsonPath('data.summary.planned_worked', 1)
            ->assertJsonPath('data.summary.notes_logged', 2);

        $this->deleteJson("/api/v1/tasks/{$other->id}")->assertNoContent();
        $this->getJson('/api/v1/reports/2026-10-27')
            ->assertJsonPath('data.summary.notes_logged', 1)
            ->assertJsonCount(1, 'data.work_log')
            ->assertJsonPath('data.work_log.0.task.id', $planned->id);
    }
}
