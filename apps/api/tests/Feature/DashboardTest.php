<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Quote;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\QuoteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_aggregates_only_the_users_data(): void
    {
        Carbon::setTestNow('2026-10-28 10:00:00'); // A Wednesday.

        $user = User::factory()->create();
        $today = '2026-10-28';

        Task::factory()->for($user)->dueOn($today)->create();
        Task::factory()->for($user)->dueOn($today)->create(['status' => 'in_progress']);
        Task::factory()->for($user)->dueOn('2026-10-20')->create();
        Task::factory()->for($user)->dueOn('2026-10-27')->completed()->create();
        Task::factory()->for($user)->dueOn('2026-10-30')->create(['status' => 'cancelled']);
        Goal::factory()->for($user)->weekly()->create();
        Task::factory()->dueOn($today)->count(5)->create();

        $this->actingAs($user)->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.date', $today)
            ->assertJsonPath('data.stats.due_today', 2)
            ->assertJsonPath('data.stats.overdue', 1)
            ->assertJsonPath('data.stats.in_progress', 1)
            ->assertJsonPath('data.stats.completed_today', 1)
            ->assertJsonPath('data.stats.active_goals', 1)
            ->assertJsonPath('data.stats.week_due_total', 3)
            ->assertJsonPath('data.stats.weekly_completion_rate', 33)
            ->assertJsonCount(2, 'data.today_tasks')
            ->assertJsonCount(1, 'data.overdue_tasks')
            ->assertJsonCount(1, 'data.weekly_goals');
    }

    public function test_weekly_rate_is_null_when_nothing_is_due(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.stats.weekly_completion_rate', null)
            ->assertJsonPath('data.quote', null);
    }

    public function test_completed_development_stays_separate_from_pending_integration(): void
    {
        $user = User::factory()->create();
        $dev = Task::factory()->for($user)->completed()->create(['title' => 'Portal V2 redesign', 'category' => 'development']);
        Task::factory()->for($user)->create(['title' => 'Portal V2 integration', 'category' => 'integration']);

        $this->actingAs($user)->getJson('/api/v1/dashboard')
            ->assertJsonPath('data.integration.development_completed', 1)
            ->assertJsonPath('data.integration.development_open', 0)
            ->assertJsonCount(1, 'data.integration.integration_pending')
            ->assertJsonPath('data.integration.integration_pending.0.title', 'Portal V2 integration');

        $this->assertSame('completed', $dev->fresh()->status->value);
    }

    public function test_random_quote_returns_text_with_attribution_and_avoids_the_excluded_one(): void
    {
        $this->seed(QuoteSeeder::class);
        $user = User::factory()->create();
        $first = Quote::firstOrFail();

        foreach (range(1, 10) as $attempt) {
            $this->actingAs($user)->getJson("/api/v1/quotes/random?exclude={$first->id}")
                ->assertOk()
                ->assertJsonStructure(['data' => ['id', 'text', 'author']])
                ->assertJsonMissingExact(['data' => ['id' => $first->id]]);
        }
    }

    public function test_random_quote_is_null_when_no_quotes_exist(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/v1/quotes/random')
            ->assertOk()
            ->assertExactJson(['data' => null]);
    }
}
