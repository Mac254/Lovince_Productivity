<?php

namespace App\Modules\Tasks\Tests\Feature;

use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MissedDayTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-03 10:00:00');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_open_tasks_move_to_today(): void
    {
        $overdue = Task::factory()->for($this->user)->dueOn('2026-11-01')->create();
        $other = Task::factory()->for($this->user)->dueOn('2026-11-02')->create();
        $done = Task::factory()->for($this->user)->dueOn('2026-11-02')->completed()->create();
        $foreign = Task::factory()->dueOn('2026-11-01')->create();

        $this->postJson('/api/v1/tasks/move-to-today', ['ids' => [$overdue->id, $done->id, $foreign->id]])
            ->assertOk()
            ->assertJsonPath('moved', 1);

        $this->assertSame('2026-11-03', $overdue->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-02', $other->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-02', $done->fresh()->due_date->toDateString());
        $this->assertSame('2026-11-01', $foreign->fresh()->due_date->toDateString());
    }

    public function test_move_to_today_validates_ids(): void
    {
        $this->postJson('/api/v1/tasks/move-to-today', ['ids' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');

        $this->postJson('/api/v1/tasks/move-to-today', ['ids' => ['x']])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.0');
    }

    public function test_a_task_can_be_completed_on_an_earlier_day(): void
    {
        $task = Task::factory()->for($this->user)->dueOn('2026-11-02')->create(['created_at' => '2026-10-30 09:00:00']);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'completed_at' => '2026-11-02'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertSame('2026-11-02', $task->fresh()->completed_at->toDateString());
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.stats.completed_today', 0);
    }

    public function test_completing_today_keeps_the_current_time(): void
    {
        $task = Task::factory()->for($this->user)->create();

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'completed_at' => '2026-11-03'])->assertOk();

        $this->assertTrue($task->fresh()->completed_at->equalTo(now()));
    }

    public function test_completion_day_must_be_past_creation_and_not_future(): void
    {
        $task = Task::factory()->for($this->user)->create(['created_at' => '2026-11-01 09:00:00']);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'completed_at' => '2026-11-04'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('completed_at');

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'completed_at' => '2026-10-31'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('completed_at');

        $this->patchJson("/api/v1/tasks/{$task->id}", ['completed_at' => '2026-11-02'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('completed_at');

        $this->assertNull($task->fresh()->completed_at);
    }

    public function test_a_completed_task_can_move_its_completion_day(): void
    {
        $task = Task::factory()->for($this->user)->completed()->create(['created_at' => '2026-10-30 09:00:00']);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['completed_at' => '2026-10-31'])->assertOk();

        $this->assertSame('2026-10-31', $task->fresh()->completed_at->toDateString());
    }

    public function test_a_saved_report_counts_a_task_completed_on_its_day(): void
    {
        $task = Task::factory()->for($this->user)->dueOn('2026-11-02')->create(['created_at' => '2026-11-01 09:00:00']);
        $this->artisan('focus:daily-report', ['--date' => '2026-11-02'])->assertSuccessful();
        $this->getJson('/api/v1/reports/2026-11-02')->assertJsonPath('data.summary.completed', 0);

        $this->patchJson("/api/v1/tasks/{$task->id}", ['status' => 'completed', 'completed_at' => '2026-11-02'])->assertOk();

        $this->getJson('/api/v1/reports/2026-11-02')->assertJsonPath('data.summary.completed', 1);
    }
}
