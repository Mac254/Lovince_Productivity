<?php

namespace App\Modules\Backup\Tests\Feature;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_backup_moves_everything_to_another_account(): void
    {
        $owner = User::factory()->create();
        $type = TaskType::factory()->for($owner)->create(['name' => 'Writing']);
        $goal = Goal::factory()->for($owner)->create(['title' => 'Ship the book']);
        $task = Task::withoutEvents(fn () => Task::factory()->for($owner)->create([
            'title' => 'Draft chapter one',
            'goal_id' => $goal->id,
            'task_type_id' => $type->id,
            'status' => TaskStatus::Completed,
            'recurrence' => TaskRecurrence::Weekly,
            'completed_at' => '2026-10-31 10:00:00',
        ]));
        TaskNote::factory()->for($task)->create(['user_id' => $owner->id, 'logged_on' => '2026-10-30', 'minutes' => 40]);
        $task->checklistItems()->make(['title' => 'Outline', 'done' => true, 'position' => 0])->forceFill(['user_id' => $owner->id])->save();
        Blocker::factory()->for($owner)->for($task)->create(['description' => 'Need the notes']);
        FocusSession::factory()->for($owner)->create(['task_id' => $task->id]);
        $owner->journalEntries()->create(['date' => '2026-10-30', 'body' => 'Wrote a lot.']);
        $owner->weeklyReviews()->create(['week_start' => '2026-10-26', 'wins' => 'Chapter one']);
        $owner->timeBlocks()->create(['task_id' => $task->id, 'title' => 'Draft chapter one', 'date' => '2026-11-02', 'start_minute' => 540, 'minutes' => 60]);

        $this->actingAs($owner);
        $export = $this->get('/api/v1/backup')->assertOk()->assertHeader('Content-Disposition');
        $backup = $export->json();
        $this->assertSame('focus-backup', $backup['format']);
        $this->assertCount(1, $backup['tasks'][0]['notes']);

        $other = User::factory()->create();
        TaskType::factory()->for($other)->create(['name' => 'Writing']);
        $other->journalEntries()->create(['date' => '2026-10-30', 'body' => 'Mine already.']);
        $this->actingAs($other);

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('backup.json', json_encode($backup))], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.tasks', 1)
            ->assertJsonPath('data.task_types', 0)
            ->assertJsonPath('data.goals', 1)
            ->assertJsonPath('data.blockers', 1)
            ->assertJsonPath('data.focus_sessions', 1)
            ->assertJsonPath('data.weekly_reviews', 1)
            ->assertJsonPath('data.time_blocks', 1)
            ->assertJsonPath('data.journal', 0)
            ->assertJsonPath('data.skipped', 1);

        $copy = $other->tasks()->sole();
        $this->assertSame('Draft chapter one', $copy->title);
        $this->assertSame($other->taskTypes()->sole()->id, $copy->task_type_id);
        $this->assertSame($other->goals()->sole()->id, $copy->goal_id);
        $this->assertNotNull($copy->completed_at);
        $this->assertNull($copy->next_occurrence_id);
        $this->assertSame(1, $copy->notes()->count());
        $this->assertTrue((bool) $copy->checklistItems()->sole()->done);
        $this->assertSame($copy->id, $other->blockers()->sole()->task_id);
        $this->assertSame($copy->id, $other->focusSessions()->sole()->task_id);
        $this->assertSame('Mine already.', $other->journalEntries()->sole()->body);
        $this->assertSame(1, $owner->tasks()->count());
    }

    public function test_a_file_that_is_not_a_backup_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('x.json', '{"format":"other","version":1}')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('x.json', 'not json')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('x.json', json_encode([
            'format' => 'focus-backup', 'version' => 1, 'tasks' => [['ref' => 1, 'title' => 'x', 'status' => 'nope', 'priority' => 'low', 'category' => 'general']],
        ]))], ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_guests_cannot_export(): void
    {
        $this->getJson('/api/v1/backup')->assertUnauthorized();
    }
}
