<?php

namespace Tests\Feature;

use App\Jobs\GenerateDailyReport;
use App\Models\Blocker;
use App\Models\DailyReport;
use App\Models\Goal;
use App\Models\Task;
use App\Models\TaskNote;
use App\Models\TaskType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReportAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_report_snapshots_the_day_and_is_regenerated_in_place(): void
    {
        Carbon::setTestNow('2026-10-28 18:00:00');
        $user = User::factory()->create();
        $today = '2026-10-28';

        Task::factory()->for($user)->dueOn($today)->completed()->create(['priority' => 'high']);
        Task::factory()->for($user)->dueOn($today)->create(['title' => 'Carried over']);
        Task::factory()->for($user)->dueOn('2026-10-20')->create();
        Blocker::factory()->for($user)->create();
        $user->journalEntries()->create(['date' => $today, 'body' => 'Good day.']);

        $this->actingAs($user)->postJson('/api/v1/reports')
            ->assertCreated()
            ->assertJsonPath('data.date', $today)
            ->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.summary.due', 2)
            ->assertJsonPath('data.summary.completion_rate', 50)
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.summary.open_blockers', 1)
            ->assertJsonPath('data.carried_over.0.title', 'Carried over')
            ->assertJsonPath('data.journal', 'Good day.');

        $this->postJson('/api/v1/reports')->assertCreated();
        $this->assertSame(1, DailyReport::count());

        $this->getJson('/api/v1/reports')->assertJsonCount(1, 'data')->assertJsonMissingPath('data.0.completed_tasks');
        $this->getJson("/api/v1/reports/{$today}")->assertOk()->assertJsonPath('data.completed_tasks.0.priority', 'high');
    }

    public function test_command_reports_for_every_user_and_reports_are_private(): void
    {
        [$a, $b] = User::factory()->count(2)->create();

        $this->artisan('focus:daily-report')->assertSuccessful();
        $this->artisan('focus:daily-report', ['--date' => today()->addDay()->toDateString()])->assertFailed();

        $this->assertSame(2, DailyReport::count());
        $this->actingAs($a)->getJson('/api/v1/reports')->assertJsonCount(1, 'data');
        DailyReport::query()->whereBelongsTo($a)->delete();
        $this->getJson('/api/v1/reports/'.today()->toDateString())->assertNotFound();
    }

    public function test_nightly_reports_are_queued_once_per_user_and_day(): void
    {
        Queue::fake();
        [$a, $b] = User::factory()->count(2)->create();

        $this->artisan('focus:daily-report', ['--date' => '2026-10-20'])->assertSuccessful();

        Queue::assertPushed(GenerateDailyReport::class, 2);
        Queue::assertPushed(fn (GenerateDailyReport $job) => $job->user->is($a) && $job->uniqueId() === "{$a->id}:2026-10-20");
        $this->assertSame(0, DailyReport::count());
    }

    public function test_metrics_describe_the_window(): void
    {
        Carbon::setTestNow('2026-10-28 12:00:00');
        $user = User::factory()->create();

        $onTime = Task::factory()->for($user)->dueOn('2026-10-28')->create(['created_at' => '2026-10-25 09:00:00']);
        $onTime->update(['status' => 'completed']);
        $late = Task::factory()->for($user)->dueOn('2026-10-20')->create(['created_at' => '2026-10-19 09:00:00']);
        $late->update(['status' => 'completed']);
        Task::factory()->for($user)->dueOn('2026-10-01')->create();

        $this->actingAs($user)->getJson('/api/v1/metrics?days=7')
            ->assertOk()
            ->assertJsonPath('data.range.start', '2026-10-22')
            ->assertJsonPath('data.totals.completed', 2)
            ->assertJsonPath('data.totals.on_time_rate', 50)
            ->assertJsonPath('data.totals.overdue_now', 1)
            ->assertJsonCount(7, 'data.daily')
            ->assertJsonPath('data.daily.6.completed', 2)
            ->assertJsonPath('data.heatmap.0.date', '2026-04-27')
            ->assertJsonPath('data.heatmap.'.(count($this->getJson('/api/v1/metrics')->json('data.heatmap')) - 1).'.count', 2);

        $this->getJson('/api/v1/metrics?days=12')->assertUnprocessable();
    }

    public function test_reports_metrics_and_dashboard_break_work_down_by_type_and_plan(): void
    {
        Carbon::setTestNow('2026-10-28 18:00:00');
        $user = User::factory()->create();
        $study = TaskType::factory()->for($user)->create(['name' => 'Study', 'tone' => 'violet']);
        TaskType::factory()->for($user)->create(['name' => 'Unused']);

        Task::factory()->for($user)->create(['task_type_id' => $study->id, 'is_highlighted' => true, 'due_date' => '2026-11-01'])
            ->update(['status' => 'completed']);
        Task::factory()->for($user)->create(['task_type_id' => $study->id, 'is_highlighted' => true, 'due_date' => null]);
        Task::factory()->for($user)->dueOn('2026-10-28')->create(['category' => 'development']);
        Task::factory()->for($user)->dueOn('2026-11-05')->create();

        $report = $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated();
        $report->assertJsonPath('data.summary.planned', 3)
            ->assertJsonPath('data.summary.planned_completed', 1)
            ->assertJsonPath('data.completed_tasks.0.type.name', 'Study')
            ->assertJsonPath('data.by_type', [
                ['key' => "type:{$study->id}", 'label' => 'Study', 'type_id' => $study->id, 'category' => null, 'tone' => 'violet', 'completed' => 1, 'open' => 0],
            ]);
        $this->assertStringContainsString('1 of 3 planned tasks were done.', $report->json('data.narrative'));

        $byType = collect($this->getJson('/api/v1/metrics?days=7')->assertOk()->json('data.by_type'))->keyBy('key');
        $this->assertSame(['completed' => 1, 'open' => 1], $byType["type:{$study->id}"] ? array_intersect_key($byType["type:{$study->id}"], ['completed' => 0, 'open' => 0]) : null);
        $this->assertSame(1, $byType['category:development']['open']);
        $this->assertSame(1, $byType['category:general']['open']);
        $this->assertCount(3, $byType, 'Empty types are left out');

        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.by_type.0.label', 'Study')->assertJsonCount(3, 'data.by_type');
    }

    public function test_missing_reports_are_filled_in_when_the_app_starts(): void
    {
        Carbon::setTestNow('2026-10-28 08:00:00');
        $user = User::factory()->create(['created_at' => '2026-10-25 12:00:00']);
        $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated();
        Carbon::setTestNow('2026-10-26 20:00:00');
        $this->postJson('/api/v1/reports')->assertCreated();
        Carbon::setTestNow('2026-10-28 08:00:00');

        $this->artisan('focus:daily-report', ['--missing' => 7])->assertSuccessful();

        $dates = DailyReport::query()->orderBy('date')->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->all();
        $this->assertSame(['2026-10-25', '2026-10-26', '2026-10-27', '2026-10-28'], $dates, 'Fills the 25th and 27th, never before sign-up');

        $this->assertStringStartsWith('2026-10-26 20:00', DailyReport::query()->whereDate('date', '2026-10-26')->value('generated_at'), 'Older saved reports are kept');
        $this->artisan('focus:daily-report', ['--missing' => 7])->expectsOutputToContain('No missing reports.');
        $this->artisan('focus:daily-report', ['--missing' => 0])->assertFailed();
    }

    public function test_heatmap_and_reports_count_logged_work(): void
    {
        Carbon::setTestNow('2026-10-28 18:00:00');
        $user = User::factory()->create();

        $long = Task::factory()->for($user)->dueOn('2026-10-28')->create();
        TaskNote::factory()->for($long)->create(['user_id' => $user->id, 'logged_on' => '2026-10-28']);
        TaskNote::factory()->for($long)->create(['user_id' => $user->id, 'logged_on' => '2026-10-27']);
        Task::factory()->for($user)->create()->update(['status' => 'completed']);
        TaskNote::factory()->create(['logged_on' => '2026-10-28']);

        $heatmap = collect($this->actingAs($user)->getJson('/api/v1/metrics')->assertOk()->json('data.heatmap'))->keyBy('date');
        $this->assertSame(['date' => '2026-10-28', 'count' => 2, 'completed' => 1, 'logged' => 1], $heatmap['2026-10-28']);
        $this->assertSame(['date' => '2026-10-27', 'count' => 1, 'completed' => 0, 'logged' => 1], $heatmap['2026-10-27']);

        $this->postJson('/api/v1/reports')->assertCreated()
            ->assertJsonPath('data.summary.planned', 1)
            ->assertJsonPath('data.summary.planned_worked', 1);
    }

    public function test_saved_reports_follow_changes_to_their_days_work_log(): void
    {
        Carbon::setTestNow('2026-10-27 23:55:00');
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create(['title' => 'Write the spec']);
        TaskNote::factory()->for($task)->create(['user_id' => $user->id, 'logged_on' => '2026-10-27', 'minutes' => 30]);
        $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated();
        $report = fn () => $this->getJson('/api/v1/reports/2026-10-27')->json('data');

        Carbon::setTestNow('2026-10-28 09:00:00');
        $late = $this->postJson("/api/v1/tasks/{$task->id}/notes", ['logged_on' => '2026-10-27', 'body' => 'Late entry', 'minutes' => 15])
            ->assertCreated()->json('data.id');

        $this->assertSame(2, $report()['summary']['notes_logged']);
        $this->assertSame(45, $report()['summary']['minutes_logged']);
        $this->assertSame('Late entry', $report()['work_log'][1]['body']);
        $this->assertStringContainsString('2 work notes were logged', $report()['narrative']);

        $this->patchJson("/api/v1/task-notes/{$late}", ['logged_on' => '2026-10-28', 'body' => 'Moved'])->assertOk();
        $this->assertSame(1, $report()['summary']['notes_logged']);

        TaskNote::query()->whereBelongsTo($user)->get()->each->delete();
        $this->assertSame([], $report()['work_log']);
        $this->assertSame(0, $report()['summary']['completed']);
    }

    public function test_reports_written_after_their_day_describe_the_day_as_it_ended(): void
    {
        Carbon::setTestNow('2026-10-26 09:00:00');
        $user = User::factory()->create(['created_at' => '2026-10-20 09:00:00']);
        $goal = Goal::factory()->for($user)->create(['status' => 'active']);
        $dueThatDay = Task::factory()->for($user)->for($goal)->dueOn('2026-10-27')->create();
        $overdue = Task::factory()->for($user)->for($goal)->dueOn('2026-10-25')->create();
        $blocker = Blocker::factory()->for($user)->create();

        Carbon::setTestNow('2026-10-27 23:58:00');
        $lastMinute = Task::factory()->for($user)->for($goal)->create();
        $lastMinute->update(['status' => 'completed']);

        Carbon::setTestNow('2026-10-28 10:00:00');
        $dueThatDay->update(['status' => 'completed']);
        $overdue->update(['status' => 'completed']);
        $blocker->update(['status' => 'resolved']);
        Task::factory()->for($user)->for($goal)->create();

        $this->artisan('focus:daily-report', ['--missing' => 1])->assertSuccessful();
        $report = $this->actingAs($user)->getJson('/api/v1/reports/2026-10-27')->assertOk();

        $report->assertJsonPath('data.summary.completed', 1)
            ->assertJsonPath('data.completed_tasks.0.id', $lastMinute->id)
            ->assertJsonPath('data.summary.due', 1)
            ->assertJsonPath('data.summary.due_completed', 0)
            ->assertJsonPath('data.carried_over.0.id', $dueThatDay->id)
            ->assertJsonPath('data.summary.overdue', 1)
            ->assertJsonPath('data.summary.open_blockers', 1)
            ->assertJsonPath('data.summary.blockers_resolved', 0)
            ->assertJsonPath('data.goals.0.completed', 1)
            ->assertJsonPath('data.goals.0.total', 3);
    }

    public function test_yesterdays_report_saved_at_2355_is_finalised_after_midnight(): void
    {
        Carbon::setTestNow('2026-10-27 23:55:00');
        $user = User::factory()->create(['created_at' => '2026-10-20 09:00:00']);
        $this->actingAs($user)->postJson('/api/v1/reports')->assertCreated()->assertJsonPath('data.summary.completed', 0);

        Carbon::setTestNow('2026-10-27 23:58:00');
        Task::factory()->for($user)->create()->update(['status' => 'completed']);

        Carbon::setTestNow('2026-10-28 00:05:00');
        $this->artisan('focus:daily-report', ['--missing' => 7])->assertSuccessful();

        $this->getJson('/api/v1/reports/2026-10-27')->assertJsonPath('data.summary.completed', 1);
        $this->artisan('focus:daily-report', ['--missing' => 7])->expectsOutputToContain('No missing reports.');
    }
}
