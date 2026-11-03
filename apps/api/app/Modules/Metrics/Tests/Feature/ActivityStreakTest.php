<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ActivityStreakTest extends TestCase
{
    use RefreshDatabase;

    public function test_streak_counts_any_activity_and_matches_the_heatmap(): void
    {
        Carbon::setTestNow('2026-11-06 15:00:00');
        $user = User::factory()->create();
        Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => '2026-11-04 10:00:00']);
        FocusSession::factory()->for($user)->create(['started_on' => '2026-11-05', 'started_at' => '2026-11-05 09:00:00', 'focused_seconds' => 600]);
        Task::factory()->for($user)->create(['status' => TaskStatus::Completed, 'completed_at' => '2026-11-01 10:00:00']);

        $metrics = $this->actingAs($user)->getJson('/api/v1/metrics?days=30')->assertOk();
        $metrics->assertJsonPath('data.consistency.current', 2)
            ->assertJsonPath('data.consistency.best', 2)
            ->assertJsonPath('data.consistency.active_today', false)
            ->assertJsonPath('data.consistency.active_days', 3);
        $day = collect($metrics->json('data.heatmap'))->firstWhere('date', '2026-11-05');
        $this->assertSame(1, $day['count']);

        $this->actingAs($user)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.streak.current', 2);
    }
}
