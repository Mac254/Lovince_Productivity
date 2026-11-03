<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Achievements\Models\Achievement;
use App\Modules\Goals\Models\Goal;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SuggestionActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_and_metrics_record_suggestions_acted_on_and_achievements(): void
    {
        Carbon::setTestNow('2026-11-03 18:00:00');
        $user = User::factory()->create(['settings' => ['suggestions' => ['enabled' => false]]]);
        $this->actingAs($user);
        $goal = Goal::factory()->for($user)->create(['title' => 'Learn Laravel']);
        $make = fn (string $title, string $category) => $user->suggestions()->create(['source' => 'custom', 'title' => $title, 'category' => $category]);

        $make('Call a friend', 'social')->update(['status' => 'done']);
        $make('Lunch walk', 'health')->update(['status' => 'accepted']);
        $make('Dance break', 'fun')->update(['status' => 'dismissed']);
        $make('Read a chapter', 'growth');
        Achievement::factory()->for($user)->create(['title' => 'Shipped v1', 'goal_id' => $goal->id, 'category' => 'growth', 'achieved_on' => '2026-11-03']);
        Achievement::factory()->for($user)->create(['achieved_on' => '2026-09-01']);

        $this->postJson('/api/v1/reports')
            ->assertCreated()
            ->assertJsonPath('data.summary.suggestions_done', 1)
            ->assertJsonPath('data.summary.suggestions_accepted', 1)
            ->assertJsonPath('data.summary.achievements', 1);
        $this->getJson('/api/v1/reports/2026-11-03')
            ->assertJsonCount(2, 'data.suggestions')
            ->assertJsonPath('data.suggestions.0.status', 'done')
            ->assertJsonPath('data.achievements.0.title', 'Shipped v1')
            ->assertJsonPath('data.achievements.0.goal.title', 'Learn Laravel');
        $this->assertStringContainsString('2 suggestions were acted on. 1 achievement was recorded.', $this->getJson('/api/v1/reports/2026-11-03')->json('data.narrative'));

        $metrics = $this->getJson('/api/v1/metrics?days=30')->assertOk();
        $metrics->assertJsonPath('data.suggestions.offered', 4)
            ->assertJsonPath('data.suggestions.acted', 2)
            ->assertJsonPath('data.suggestions.dismissed', 1)
            ->assertJsonPath('data.suggestions.open_now', 1)
            ->assertJsonPath('data.suggestions.acceptance_rate', 67)
            ->assertJsonPath('data.achievements.count', 1)
            ->assertJsonPath('data.achievements.from_goals', 1)
            ->assertJsonPath('data.achievements.latest.0.title', 'Shipped v1');
        $this->assertSame(1, collect($metrics->json('data.suggestions.by_category'))->firstWhere('key', 'social')['acted']);
        $insights = collect($metrics->json('data.insights'))->pluck('key');
        $this->assertContains('suggestions', $insights);
        $this->assertContains('achievements', $insights);
    }
}
