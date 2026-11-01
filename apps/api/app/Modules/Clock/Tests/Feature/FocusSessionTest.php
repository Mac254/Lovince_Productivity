<?php

namespace App\Modules\Clock\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FocusSessionTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'started_at' => now()->subMinutes(30)->toIso8601String(),
            'started_on' => today()->toDateString(),
            'planned_minutes' => 25,
            'focused_seconds' => 1500,
            'completed' => true,
        ], $overrides);
    }

    public function test_records_a_session_and_lists_the_day(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/focus-sessions', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.minutes', 25)
            ->assertJsonPath('data.task', null)
            ->assertJsonPath('data.task_note_id', null);

        $this->getJson('/api/v1/focus-sessions')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.minutes', 25)
            ->assertJsonPath('meta.count', 1)
            ->assertJsonPath('meta.completed', 1)
            ->assertJsonCount(7, 'meta.week')
            ->assertJsonPath('meta.week.6.date', today()->toDateString())
            ->assertJsonPath('meta.week.6.minutes', 25);
    }

    public function test_a_session_on_a_task_adds_a_work_log_entry(): void
    {
        $user = User::factory()->create();
        $task = Task::factory()->for($user)->create();
        $this->actingAs($user);

        $id = $this->postJson('/api/v1/focus-sessions', $this->payload([
            'task_id' => $task->id, 'focused_seconds' => 610, 'completed' => false,
        ]))->assertCreated()
            ->assertJsonPath('data.task.title', $task->title)
            ->json('data.task_note_id');

        $note = $task->notes()->sole();
        $this->assertSame($id, $note->id);
        $this->assertSame(10, $note->minutes);
        $this->assertSame('Focus session, stopped early (10 min)', $note->body);

        $session = FocusSession::query()->sole();
        $this->deleteJson("/api/v1/focus-sessions/{$session->id}")->assertNoContent();
        $this->assertModelMissing($note);
    }

    public function test_rejects_short_sessions_and_other_users_tasks(): void
    {
        $user = User::factory()->create();
        $other = Task::factory()->create();
        $this->actingAs($user);

        $this->postJson('/api/v1/focus-sessions', $this->payload(['focused_seconds' => 30]))
            ->assertUnprocessable()->assertJsonValidationErrors('focused_seconds');
        $this->postJson('/api/v1/focus-sessions', $this->payload(['task_id' => $other->id]))
            ->assertUnprocessable()->assertJsonValidationErrors('task_id');
        $this->assertDatabaseCount('focus_sessions', 0);
    }

    public function test_sessions_are_private(): void
    {
        $session = FocusSession::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/focus-sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->deleteJson("/api/v1/focus-sessions/{$session->id}")->assertNotFound();

        $this->actingAs($session->user);
        $this->deleteJson("/api/v1/focus-sessions/{$session->id}")->assertNoContent();
        $this->assertModelMissing($session);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/focus-sessions')->assertUnauthorized();
    }

    public function test_streak_goal_metrics_and_dashboard_include_focus_time(): void
    {
        $user = User::factory()->create(['settings' => ['timer' => ['daily_goal' => '60']]]);
        foreach ([0, 1, 2, 4] as $back) {
            FocusSession::factory()->for($user)->create([
                'started_on' => today()->subDays($back)->toDateString(),
                'started_at' => today()->subDays($back)->setTime(9, 30),
                'focused_seconds' => 3600,
            ]);
        }
        $this->actingAs($user);

        $this->getJson('/api/v1/focus-sessions')
            ->assertJsonPath('meta.goal_minutes', 60)
            ->assertJsonPath('meta.streak', 3);

        $this->getJson('/api/v1/metrics?days=7')
            ->assertOk()
            ->assertJsonPath('data.focus.minutes', 240)
            ->assertJsonPath('data.focus.sessions', 4)
            ->assertJsonPath('data.focus.goal_days', 4)
            ->assertJsonPath('data.focus.best_hour', 9)
            ->assertJsonPath('data.daily.6.focus', 60);

        $this->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.focus.minutes', 60)
            ->assertJsonPath('data.focus.goal_minutes', 60)
            ->assertJsonPath('data.focus.streak', 3);
    }
}
