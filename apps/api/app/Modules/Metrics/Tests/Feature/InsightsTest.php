<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class InsightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 15:00:00');
    }

    public function test_metrics_explain_what_stands_out(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (range(1, 10) as $i) {
            $task = Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'category' => 'development']);
            $task->forceFill(['completed_at' => '2026-11-03 10:00:00', 'created_at' => '2026-11-01 10:00:00'])->save();
        }
        Task::factory()->for($user)->dueOn('2026-11-01')->create(['status' => TaskStatus::NotStarted]);
        FocusSession::factory()->for($user)->create(['started_on' => '2026-11-05', 'started_at' => '2026-11-05 09:00:00', 'focused_seconds' => 3000]);

        $response = $this->getJson('/api/v1/metrics?days=30')->assertOk();

        $insights = collect($response->json('data.insights'))->keyBy('key');
        $this->assertSame('warning', $insights['overdue']['tone']);
        $this->assertSame('1 open task is overdue.', $insights['overdue']['text']);
        $this->assertSame('positive', $insights['pace']['tone']);
        $this->assertSame('You finish the most on Tuesdays.', $insights['weekday']['text']);
        $this->assertSame('100% of what you finish is Development work.', $insights['category']['text']);

        $day = collect($response->json('data.heatmap'))->firstWhere('date', '2026-11-05');
        $this->assertSame(50, $day['focus_minutes']);
        $this->assertSame(2, $day['count']);
    }

    public function test_best_weekday_needs_two_weeks_and_a_clear_winner(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['2026-11-03', '2026-11-04'] as $day) {
            foreach (range(1, 5) as $i) {
                Task::factory()->for($user)->create(['status' => TaskStatus::Completed])->forceFill(['completed_at' => "{$day} 10:00:00"])->save();
            }
        }

        foreach ([7, 30] as $days) {
            $insights = collect($this->getJson("/api/v1/metrics?days={$days}")->json('data.insights'))->keyBy('key');
            $this->assertArrayNotHasKey('weekday', $insights->all());
        }
    }

    public function test_estimate_accuracy_only_counts_tasks_with_time_logged(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (range(1, 4) as $i) {
            $task = Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'estimate_minutes' => 60]);
            $task->forceFill(['completed_at' => '2026-11-05 10:00:00'])->save();
            if ($i <= 3) {
                TaskNote::factory()->for($user)->for($task)->create(['logged_on' => '2026-11-05', 'minutes' => 60]);
            }
        }

        $response = $this->getJson('/api/v1/metrics?days=7')->assertOk();

        $response->assertJsonPath('data.totals.estimated_tasks', 3)
            ->assertJsonPath('data.totals.estimated_minutes', 180)
            ->assertJsonPath('data.totals.estimate_accuracy', 100);
        $insights = collect($response->json('data.insights'))->keyBy('key');
        $this->assertSame('Your time estimates are on target.', $insights['estimates']['text']);
    }

    public function test_quiet_accounts_get_no_insights(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/metrics?days=30')->assertOk()->assertJsonPath('data.insights', []);
    }
}
