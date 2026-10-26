<?php

namespace Tests\Feature;

use App\Jobs\GenerateDailyReport;
use App\Models\Blocker;
use App\Models\DailyReport;
use App\Models\Task;
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
}
