<?php

namespace App\Modules\Planner\Tests\Feature;

use App\Modules\Planner\Models\TimeBlock;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskType;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_time_is_grouped_by_type_and_skips_cancelled_and_future_blocks(): void
    {
        Carbon::setTestNow('2026-11-24 10:30:00');
        $user = User::factory()->create();
        $work = TaskType::factory()->for($user)->create(['name' => 'Work', 'tone' => 'rose']);
        $coding = Task::factory()->for($user)->create(['title' => 'Code', 'task_type_id' => $work->id]);
        $skipped = Task::factory()->for($user)->create(['title' => 'Gym', 'status' => TaskStatus::Cancelled]);
        $block = fn (string $date, int $start, int $minutes, array $extra) => TimeBlock::factory()->for($user)
            ->create(['date' => $date, 'start_minute' => $start, 'minutes' => $minutes, ...$extra]);

        $block('2026-11-23', 540, 120, ['task_id' => $coding->id]);
        $block('2026-11-23', 720, 60, ['title' => 'Lunch']);
        $block('2026-11-23', 300, 30, ['task_id' => $skipped->id]);
        $block('2026-11-24', 600, 90, ['task_id' => $coding->id]);
        $block('2026-11-25', 600, 90, ['task_id' => $coding->id]);
        $block('2026-11-16', 600, 45, ['title' => 'Lunch']);

        $this->actingAs($user)->getJson('/api/v1/time?range=week&date=2026-11-24')
            ->assertOk()
            ->assertJsonPath('data.from', '2026-11-23')
            ->assertJsonPath('data.total', 210)
            ->assertJsonPath('data.previous_total', 45)
            ->assertJsonPath('data.daily_average', 105)
            ->assertJsonPath('data.previous_average', 6)
            ->assertJsonPath('data.categories.0.name', 'Work')
            ->assertJsonPath('data.categories.0.minutes', 150)
            ->assertJsonPath('data.categories.1.key', 'other')
            ->assertJsonPath('data.buckets.0.minutes.'.$work->id, 120)
            ->assertJsonPath('data.activities.0.title', 'Code')
            ->assertJsonCount(7, 'data.buckets');

        $this->getJson('/api/v1/time?range=day&date=2026-11-23')
            ->assertJsonPath('data.buckets.9.minutes.'.$work->id, 60)
            ->assertJsonPath('data.buckets.12.minutes.other', 60)
            ->assertJsonCount(24, 'data.buckets');
    }

    public function test_the_range_is_validated(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/time?range=year')->assertUnprocessable();
    }
}
