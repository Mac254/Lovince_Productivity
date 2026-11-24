<?php

namespace App\Modules\Planner\Tests\Feature;

use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HabitTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_mixed_slot_takes_the_activity_done_least_recently(): void
    {
        Carbon::setTestNow('2026-11-24 06:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        $id = $this->postJson('/api/v1/schedules', ['name' => 'Mornings', 'items' => [
            ['title' => 'Exercise', 'alternatives' => [' Run ', 'Strength', 'Yoga', 'Run', ''], 'start_minute' => 315, 'minutes' => 30],
        ]])->assertCreated()
            ->assertJsonPath('data.items.0.alternatives', ['Run', 'Strength', 'Yoga'])
            ->json('data.id');

        $done = fn (string $title, string $date) => Task::factory()->for($user)->create([
            'title' => $title, 'habit' => 'Exercise', 'due_date' => $date, 'status' => TaskStatus::Completed,
        ]);
        $done('Run', '2026-11-22');
        $done('Strength', '2026-11-20');
        $done('Yoga', '2026-11-23');

        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => '2026-11-24'])
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Strength');
        $this->assertSame('Exercise', Task::query()->whereDate('due_date', '2026-11-24')->value('habit'));

        // Planning the day again keeps the activity it already took.
        $this->postJson("/api/v1/schedules/{$id}/apply", ['date' => '2026-11-24', 'replace' => true])
            ->assertJsonPath('data.0.title', 'Strength');
        $this->assertSame(4, Task::count());
    }

    public function test_habits_report_rates_streaks_and_days_and_stay_out_of_the_task_list(): void
    {
        Carbon::setTestNow('2026-11-24 06:00:00');
        $user = User::factory()->create();
        $user->schedules()->create(['name' => 'Daily'])->items()->create(['title' => 'Read', 'start_minute' => 300, 'minutes' => 20]);
        $day = fn (string $date, TaskStatus $status) => Task::factory()->for($user)->create([
            'title' => 'Read', 'habit' => 'Read', 'due_date' => $date, 'status' => $status,
        ]);
        $day('2026-11-19', TaskStatus::Completed);
        $day('2026-11-20', TaskStatus::Cancelled);
        $day('2026-11-21', TaskStatus::Completed);
        $day('2026-11-22', TaskStatus::Completed);
        $day('2026-11-23', TaskStatus::Completed);
        $day('2026-11-24', TaskStatus::NotStarted);
        Task::factory()->for($user)->create(['title' => 'Ship the blog']);

        $this->actingAs($user)->getJson('/api/v1/habits?days=14')
            ->assertOk()
            ->assertJsonPath('data.habits.0.title', 'Read')
            ->assertJsonPath('data.habits.0.scheduled', true)
            ->assertJsonPath('data.habits.0.done', 4)
            ->assertJsonPath('data.habits.0.planned', 5)
            ->assertJsonPath('data.habits.0.rate', 80)
            ->assertJsonPath('data.habits.0.streak', 3)
            ->assertJsonPath('data.habits.0.best_streak', 3)
            ->assertJsonPath('data.habits.0.today', 'open')
            ->assertJsonPath('data.habits.0.days.1', ['date' => '2026-11-20', 'status' => 'skipped']);

        $this->getJson('/api/v1/tasks?habits=hide')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/tasks?habits=current')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/habits?days=7')->assertUnprocessable();
    }
}
