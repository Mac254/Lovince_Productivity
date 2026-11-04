<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Drives a month of use through the API (tasks, completions, work log, focus, goals, journal) and checks that
 * every screen that summarises it (metrics, dashboard, weekly review, daily report, goals, suggestions) agrees
 * with the numbers worked out from the same plan.
 */
class DataFlowSimulationTest extends TestCase
{
    use RefreshDatabase;

    private const TODAY = 29;

    /** @var list<string> */
    private array $mismatches = [];

    public function test_every_summary_agrees_with_a_simulated_month(): void
    {
        $user = User::factory()->create();
        $at = fn (int $day, string $time) => Carbon::setTestNow(sprintf('2026-11-%02d %s', $day, $time));

        $at(1, '08:00:00');
        $goal = $this->actingAs($user)->postJson('/api/v1/goals', ['title' => 'Ship the beta', 'timeframe' => 'long_term'])->assertCreated()->json('data.id');

        $plan = [];
        foreach (range(1, self::TODAY) as $day) {
            $at($day, '09:00:00');
            $date = sprintf('2026-11-%02d', $day);
            $task = [
                'day' => $day,
                'priority' => ['low', 'medium', 'high', 'urgent'][$day % 4],
                'category' => $day % 2 ? 'development' : 'general',
                'goal' => $day % 2 === 1,
                'done' => $day % 3 !== 0,
                'note' => $day % 2 === 0 || $day === self::TODAY,
                'focus' => $day % 4 === 0,
            ];
            $id = $this->postJson('/api/v1/tasks', [
                'title' => "Task {$day}",
                'due_date' => $date,
                'priority' => $task['priority'],
                'category' => $task['category'],
                'goal_id' => $task['goal'] ? $goal : null,
            ])->assertCreated()->json('data.id');

            if ($task['note']) {
                $this->postJson("/api/v1/tasks/{$id}/notes", ['body' => "Worked on {$day}", 'logged_on' => $date, 'minutes' => 30])->assertCreated();
            }
            if ($task['focus']) {
                FocusSession::factory()->for($user)->create([
                    'started_on' => $date,
                    'started_at' => "{$date} 10:00:00",
                    'ended_at' => "{$date} 10:25:00",
                    'focused_seconds' => 1500,
                ]);
            }
            if ($task['done']) {
                $at($day, '17:00:00');
                $this->patchJson("/api/v1/tasks/{$id}", ['status' => 'completed'])->assertOk();
            }
            $plan[] = $task;
        }

        $at(self::TODAY, '20:00:00');
        $this->postJson('/api/v1/tasks', ['title' => 'Next Sunday', 'due_date' => '2026-12-06'])->assertCreated();
        $this->putJson('/api/v1/journal/2026-11-29', ['body' => 'Wrapped up the month.'])->assertSuccessful();
        $this->postJson('/api/v1/goals', ['title' => 'Learn Rust', 'timeframe' => 'long_term'])->assertCreated();

        $plan = collect($plan);
        $between = fn (int $from, int $to) => $plan->filter(fn (array $t) => $t['day'] >= $from && $t['day'] <= $to);
        $week = $between(23, 29);
        $overdue = $plan->filter(fn (array $t) => ! $t['done'] && $t['day'] < self::TODAY)->count();

        $metrics = $this->getJson('/api/v1/metrics?days=7')->assertOk()->json('data');
        $totals = $metrics['totals'];
        $this->same('metrics completed', $week->where('done', true)->count(), $totals['completed']);
        $this->same('metrics completed previous', $between(16, 22)->where('done', true)->count(), $totals['completed_previous']);
        $this->same('metrics overdue', $overdue, $totals['overdue_now']);
        $this->same('metrics minutes logged', $week->where('note', true)->count() * 30, $totals['minutes_logged']);
        $this->same('metrics on time rate', 100, $totals['on_time_rate']);
        $this->same('daily completed adds up', $totals['completed'], array_sum(array_column($metrics['daily'], 'completed')));
        $this->same('daily minutes add up', $totals['minutes_logged'], array_sum(array_column($metrics['daily'], 'minutes')));
        $this->same('daily focus adds up', $metrics['focus']['minutes'], array_sum(array_column($metrics['daily'], 'focus')));
        $this->same('priorities add up', $totals['completed'], array_sum(array_column($metrics['by_priority'], 'completed')));
        $this->same('categories add up', $totals['completed'], array_sum(array_column($metrics['by_category'], 'completed')));
        $this->same('focus minutes', $week->where('focus', true)->count() * 25, $metrics['focus']['minutes']);

        $heatmap = collect($metrics['heatmap'])->keyBy('date');
        foreach ($metrics['daily'] as $day) {
            $this->same("heatmap completed on {$day['date']}", $day['completed'], $heatmap[$day['date']]['completed']);
        }
        $active = $plan->map(fn (array $t) => $t['done'] || $t['note'] || $t['focus'])->values()->all();
        $streak = 0;
        for ($i = count($active) - 1; $i >= 0 && $active[$i]; $i--) {
            $streak++;
        }
        $this->same('metrics streak', $streak, $metrics['consistency']['current']);

        $goalTasks = $plan->where('goal', true);
        $progress = (int) round($goalTasks->where('done', true)->count() / $goalTasks->count() * 100);
        $metricsGoal = collect($metrics['goals'])->firstWhere('id', $goal);
        $this->same('metrics goal progress', $progress, $metricsGoal['progress']);
        $this->same('goal page progress', $progress, $this->getJson("/api/v1/goals/{$goal}")->json('data.progress'));

        $insights = collect($metrics['insights'])->keyBy('key');
        $this->same('overdue insight', "{$overdue} open tasks are overdue.", $insights['overdue']['text'] ?? null);

        $dashboard = $this->getJson('/api/v1/dashboard')->assertOk()->json('data');
        $this->same('dashboard streak', $streak, $dashboard['streak']['current']);
        $this->same('dashboard overdue', $overdue, $dashboard['stats']['overdue']);
        $this->same('dashboard completed today', 1, $dashboard['stats']['completed_today']);
        $this->same('dashboard week due', $week->count(), $dashboard['stats']['week_due_total']);
        $this->same('dashboard week done', $week->where('done', true)->count(), $dashboard['stats']['week_due_completed']);
        $this->same('dashboard completed this week', $week->where('done', true)->count(), $dashboard['stats']['completed_this_week']);
        $this->same('dashboard goal progress', $progress, collect($dashboard['active_goals'])->firstWhere('id', $goal)['progress'] ?? null);
        $this->same('tasks filtered to this week', $week->count(), $this->getJson('/api/v1/tasks?due=week&per_page=50')->json('meta.total'));

        $review = $this->getJson('/api/v1/reviews/2026-11-23')->assertOk()->json('data');
        $this->same('review completed', $week->where('done', true)->count(), $review['totals']['completed']);
        $this->same('review minutes logged', $week->where('note', true)->count() * 30, $review['totals']['minutes_logged']);
        $this->same('review minutes on the last day', 30, collect($review['days'])->firstWhere('date', '2026-11-29')['minutes_logged']);
        $this->same('review focus', $metrics['focus']['minutes'], $review['totals']['focus_minutes']);
        $this->same('review journal', ['2026-11-29'], array_column($review['journal'], 'date'));
        $this->same('review next week', ['Next Sunday'], array_column($review['next_week'], 'title'));
        $this->same('review goal progress', $progress, collect($review['goals'])->firstWhere('id', $goal)['progress']);

        $report = $this->postJson('/api/v1/reports')->assertCreated()->json('data.summary');
        $this->same('report completed', 1, $report['completed']);
        $this->same('report minutes', 30, $report['minutes_logged']);

        $this->postJson('/api/v1/suggestions/refresh')->assertSuccessful();
        $titles = collect($this->getJson('/api/v1/suggestions?per_page=50')->json('data'))->pluck('title');
        $this->same('overdue suggestion', true, $titles->contains("Reschedule {$overdue} overdue tasks"));
        $this->same('busy goal not called quiet', false, $titles->contains('Give “Ship the beta” 30 minutes today'));
        $this->same('goal made today not called quiet', false, $titles->contains('Give “Learn Rust” 30 minutes today'));

        $this->assertSame([], $this->mismatches);
    }

    private function same(string $what, mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            $this->mismatches[] = "{$what}: expected ".json_encode($expected).', got '.json_encode($actual);
        }
    }
}
