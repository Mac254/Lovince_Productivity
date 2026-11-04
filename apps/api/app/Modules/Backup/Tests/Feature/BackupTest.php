<?php

namespace App\Modules\Backup\Tests\Feature;

use App\Modules\Blockers\Models\Blocker;
use App\Modules\Clock\Models\FocusSession;
use App\Modules\Goals\Models\Goal;
use App\Modules\Reports\Models\DailyReport;
use App\Modules\Reports\Services\DailyReportGenerator;
use App\Modules\Tasks\Enums\TaskRecurrence;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
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
        $owner->achievements()->create(['title' => 'Finished the book', 'achieved_on' => '2026-11-02', 'category' => 'growth', 'goal_id' => $goal->id, 'task_id' => $task->id]);

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
            ->assertJsonPath('data.achievements', 1)
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
        $achievement = $other->achievements()->sole();
        $this->assertSame($copy->goal_id, $achievement->goal_id);
        $this->assertSame($copy->id, $achievement->task_id);
        $this->assertSame('Mine already.', $other->journalEntries()->sole()->body);
        $this->assertSame(1, $owner->tasks()->count());
    }

    public function test_importing_a_backup_again_adds_nothing(): void
    {
        $owner = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create(['title' => 'Ship the book']);
        $task = Task::factory()->for($owner)->create(['title' => 'Draft chapter one', 'goal_id' => $goal->id]);
        Blocker::factory()->for($owner)->for($task)->create(['description' => 'Need the notes']);
        FocusSession::factory()->for($owner)->create(['task_id' => $task->id]);
        $owner->timeBlocks()->create(['task_id' => $task->id, 'title' => 'Draft', 'date' => '2026-11-02', 'start_minute' => 540, 'minutes' => 60]);
        $owner->achievements()->create(['title' => 'Finished', 'achieved_on' => '2026-11-02', 'category' => 'growth']);
        $owner->schedules()->create(['name' => 'Workday'])->items()->create(['title' => 'Write', 'start_minute' => 540, 'minutes' => 60]);
        $owner->suggestions()->create(['source' => 'custom', 'title' => 'Call a friend', 'category' => 'social']);
        $this->actingAs($owner);
        $backup = $this->getJson('/api/v1/backup')->json();
        $upload = fn () => ['file' => UploadedFile::fake()->createWithContent('backup.json', json_encode($backup))];

        $added = $this->post('/api/v1/backup', $upload(), ['Accept' => 'application/json'])->assertOk()->json('data');

        $this->assertSame(0, array_sum($this->except($added, 'skipped')));
        $this->assertSame(1, $owner->tasks()->count());
        $this->assertSame(1, $owner->goals()->count());
        $this->assertSame(1, $owner->focusSessions()->count());
        $this->assertSame(1, $owner->blockers()->count());
        $this->assertSame(1, $owner->timeBlocks()->count());
        $this->assertSame(1, $owner->achievements()->count());
        $this->assertSame(1, $owner->schedules()->count());
        $this->assertSame(1, $owner->suggestions()->count());

        $other = User::factory()->create();
        $this->actingAs($other);
        $first = $this->post('/api/v1/backup', $upload(), ['Accept' => 'application/json'])->assertOk()->json('data');
        $second = $this->post('/api/v1/backup', $upload(), ['Accept' => 'application/json'])->assertOk()->json('data');

        $this->assertSame(1, $first['focus_sessions']);
        $this->assertSame(0, array_sum($this->except($second, 'skipped')));
        $this->assertSame(1, $other->tasks()->count());
        $this->assertSame(1, $other->focusSessions()->count());
    }

    public function test_schedules_suggestions_and_covers_come_across_and_saved_reports_are_rebuilt(): void
    {
        Carbon::setTestNow('2026-11-03 12:00:00');
        $owner = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();
        $task = Task::withoutEvents(fn () => Task::factory()->for($owner)->create([
            'cover' => 'focus-2',
            'status' => TaskStatus::Completed,
            'completed_at' => '2026-10-31 10:00:00',
            'created_at' => '2026-10-30 09:00:00',
        ]));
        $schedule = $owner->schedules()->create(['name' => 'Workday', 'days' => [1, 2]]);
        $schedule->items()->create(['title' => 'Write', 'goal_id' => $goal->id, 'start_minute' => 540, 'minutes' => 60]);
        $owner->suggestions()->create(['source' => 'insight', 'key' => 'plan:1', 'title' => 'Plan', 'category' => 'focus', 'status' => 'accepted', 'action' => ['type' => 'apply_schedule', 'schedule_id' => $schedule->id, 'date' => '2026-11-04']]);
        $owner->suggestions()->create(['source' => 'insight', 'key' => 'fresh:1', 'title' => 'Fresh', 'category' => 'focus']);
        $owner->suggestions()->create(['source' => 'custom', 'title' => 'Walk', 'category' => 'health', 'task_id' => $task->id, 'action' => ['type' => 'task', 'goal_id' => $goal->id]]);
        $this->actingAs($owner);
        $backup = $this->getJson('/api/v1/backup')->assertJsonPath('version', 2)->json();
        $this->assertSame('focus-2', $backup['tasks'][0]['cover']);
        $this->assertCount(2, $backup['suggestions']);

        $other = User::factory()->create();
        app(DailyReportGenerator::class)->generate($other, Carbon::parse('2026-10-31'));
        $this->assertSame(0, DailyReport::query()->whereBelongsTo($other)->sole()->completed_count);
        $this->actingAs($other);

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('backup.json', json_encode($backup))], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.schedules', 1)
            ->assertJsonPath('data.suggestions', 2);

        $copy = $other->schedules()->with('items')->sole();
        $this->assertSame([1, 2], $copy->days);
        $this->assertSame($other->goals()->sole()->id, $copy->items->sole()->goal_id);
        $this->assertSame('focus-2', $other->tasks()->sole()->cover);
        $plan = $other->suggestions()->where('key', 'plan:1')->sole();
        $this->assertSame($copy->id, $plan->action['schedule_id']);
        $this->assertNotNull($plan->accepted_at);
        $walk = $other->suggestions()->where('title', 'Walk')->sole();
        $this->assertSame($other->tasks()->sole()->id, $walk->task_id);
        $this->assertSame($other->goals()->sole()->id, $walk->action['goal_id']);
        $this->assertSame(1, DailyReport::query()->whereBelongsTo($other)->sole()->completed_count);
    }

    public function test_version_one_backups_still_import(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post('/api/v1/backup', ['file' => UploadedFile::fake()->createWithContent('x.json', json_encode([
            'format' => 'focus-backup', 'version' => 1, 'journal' => [['date' => '2026-11-01', 'body' => 'Old file.']],
        ]))], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.journal', 1);
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function except(array $counts, string $key): array
    {
        unset($counts[$key]);

        return $counts;
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
