<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TodayMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_can_cover_today_alone_against_yesterday(): void
    {
        Carbon::setTestNow('2026-11-24 15:00:00');
        $user = User::factory()->create();
        $done = fn (string $at) => Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => $at]);
        $done('2026-11-24 09:00:00');
        $done('2026-11-24 11:00:00');
        $done('2026-11-23 10:00:00');
        $done('2026-11-20 10:00:00');

        $this->actingAs($user)->getJson('/api/v1/metrics?days=1')
            ->assertOk()
            ->assertJsonPath('data.range', ['days' => 1, 'start' => '2026-11-24', 'end' => '2026-11-24'])
            ->assertJsonPath('data.totals.completed', 2)
            ->assertJsonPath('data.totals.completed_previous', 1);
    }
}
