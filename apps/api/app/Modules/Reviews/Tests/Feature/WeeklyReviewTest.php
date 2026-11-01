<?php

namespace App\Modules\Reviews\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Reviews\Models\WeeklyReview;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeeklyReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-06 15:00:00');
    }

    public function test_the_week_is_summarised(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $done = Task::factory()->for($user)->create(['title' => 'Ship it', 'status' => TaskStatus::Completed]);
        $done->forceFill(['completed_at' => '2026-11-03 10:00:00'])->save();
        Task::factory()->for($user)->dueOn('2026-11-04')->create(['title' => 'Late', 'status' => TaskStatus::NotStarted]);
        Task::factory()->for($user)->dueOn('2026-11-10')->create(['title' => 'Next', 'status' => TaskStatus::NotStarted]);
        TaskNote::factory()->for($done)->create(['user_id' => $user->id, 'logged_on' => '2026-11-03', 'minutes' => 45]);
        FocusSession::factory()->for($user)->create(['started_on' => '2026-11-04', 'started_at' => '2026-11-04 09:00:00', 'focused_seconds' => 1800]);
        $user->journalEntries()->create(['date' => '2026-11-05', 'body' => 'A good week.']);
        Task::factory()->create(['title' => 'Someone else']);

        $this->getJson('/api/v1/reviews/2026-11-04')
            ->assertOk()
            ->assertJsonPath('data.week.start', '2026-11-02')
            ->assertJsonPath('data.week.end', '2026-11-08')
            ->assertJsonPath('data.totals.completed', 1)
            ->assertJsonPath('data.totals.minutes_logged', 45)
            ->assertJsonPath('data.totals.focus_minutes', 30)
            ->assertJsonPath('data.days.1.completed', 1)
            ->assertJsonPath('data.days.2.focus_minutes', 30)
            ->assertJsonPath('data.completed.0.title', 'Ship it')
            ->assertJsonPath('data.carry_over.0.title', 'Late')
            ->assertJsonPath('data.next_week.0.title', 'Next')
            ->assertJsonPath('data.journal.0.excerpt', 'A good week.')
            ->assertJsonPath('data.review', null);
    }

    public function test_the_reflection_is_saved_and_marked_done(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/api/v1/reviews/2026-11-06', ['wins' => ' Shipped ', 'challenges' => '', 'next_focus' => 'Rest'])
            ->assertOk()
            ->assertJsonPath('data.review.wins', 'Shipped')
            ->assertJsonPath('data.review.challenges', null)
            ->assertJsonPath('data.review.completed_at', null);

        $this->putJson('/api/v1/reviews/2026-11-02', ['completed' => true])->assertOk()->assertJsonPath('data.review.next_focus', 'Rest');

        $this->assertSame(1, WeeklyReview::count());
        $this->getJson('/api/v1/reviews')->assertJsonCount(1, 'data')->assertJsonPath('data.0.week_start', '2026-11-02');
    }

    public function test_future_weeks_and_bad_dates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/reviews/2026-11-09')->assertNotFound();
        $this->putJson('/api/v1/reviews/2026-02-30', [])->assertNotFound();
        $this->getJson('/api/v1/reviews/2026-11-02')->assertOk();
    }

    public function test_reviews_are_private(): void
    {
        User::factory()->create()->weeklyReviews()->create(['week_start' => '2026-11-02', 'wins' => 'Secret']);
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/reviews/2026-11-02')->assertJsonPath('data.review', null);
        $this->getJson('/api/v1/reviews')->assertJsonCount(0, 'data');
    }

    public function test_the_dashboard_prompts_from_the_review_day_until_done(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.review', ['week_start' => '2026-11-02', 'due' => true, 'done' => false]);

        $this->patchJson('/api/v1/settings', ['review' => ['day' => 'sunday']])->assertOk();
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.review.due', false);

        $this->patchJson('/api/v1/settings', ['review' => ['day' => 'friday']])->assertOk();
        $this->putJson('/api/v1/reviews/2026-11-02', ['completed' => true])->assertOk();
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.review.done', true);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/reviews/2026-11-02')->assertUnauthorized();
    }
}
