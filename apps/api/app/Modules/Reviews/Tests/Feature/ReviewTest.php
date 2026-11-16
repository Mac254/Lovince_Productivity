<?php

namespace App\Modules\Reviews\Tests\Feature;

use App\Modules\Clock\Models\FocusSession;
use App\Modules\Reviews\Models\Review;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Modules\Tasks\Models\Task;
use App\Modules\Tasks\Models\TaskNote;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReviewTest extends TestCase
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
        Task::factory()->for($user)->dueOn('2026-11-05')->create(['title' => 'Morning run', 'status' => TaskStatus::Cancelled, 'cancel_reason' => 'Rain']);
        TaskNote::factory()->for($done)->create(['user_id' => $user->id, 'logged_on' => '2026-11-03', 'minutes' => 45]);
        FocusSession::factory()->for($user)->create(['started_on' => '2026-11-04', 'started_at' => '2026-11-04 09:00:00', 'focused_seconds' => 1800]);
        $user->journalEntries()->create(['date' => '2026-11-05', 'body' => 'A good week.']);
        Task::factory()->create(['title' => 'Someone else']);

        $this->getJson('/api/v1/reviews/week/2026-11-04')
            ->assertOk()
            ->assertJsonPath('data.period.kind', 'week')
            ->assertJsonPath('data.period.start', '2026-11-02')
            ->assertJsonPath('data.period.end', '2026-11-08')
            ->assertJsonPath('data.totals.completed', 1)
            ->assertJsonPath('data.totals.minutes_logged', 45)
            ->assertJsonPath('data.totals.focus_minutes', 30)
            ->assertJsonCount(7, 'data.buckets')
            ->assertJsonPath('data.buckets.1.completed', 1)
            ->assertJsonPath('data.buckets.2.focus_minutes', 30)
            ->assertJsonPath('data.completed.0.title', 'Ship it')
            ->assertJsonPath('data.carry_over.0.title', 'Late')
            ->assertJsonPath('data.next.0.title', 'Next')
            ->assertJsonPath('data.skipped.0.cancel_reason', 'Rain')
            ->assertJsonPath('data.journal.0.excerpt', 'A good week.')
            ->assertJsonPath('data.review', null)
            ->assertJsonPath('data.shorter', []);
    }

    public function test_the_old_week_address_still_works(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/reviews/2026-11-04')->assertOk()->assertJsonPath('data.period.start', '2026-11-02');
        $this->putJson('/api/v1/reviews/2026-11-04', ['wins' => 'Shipped'])->assertOk()->assertJsonPath('data.review.wins', 'Shipped');
        $this->getJson('/api/v1/reviews/week/2026-11-02')->assertJsonPath('data.review.wins', 'Shipped');
    }

    public function test_a_month_has_a_bar_per_day_and_its_weekly_reviews(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $done = Task::factory()->for($user)->create(['status' => TaskStatus::Completed]);
        $done->forceFill(['completed_at' => '2026-11-03 10:00:00'])->save();
        $user->reviews()->create(['starts_on' => '2026-11-02', 'wins' => 'A strong first week']);
        $user->reviews()->create(['starts_on' => '2026-10-26', 'wins' => 'October’s last week']);

        $this->getJson('/api/v1/reviews/month/2026-11-06')
            ->assertOk()
            ->assertJsonPath('data.period.kind', 'month')
            ->assertJsonPath('data.period.label', 'November 2026')
            ->assertJsonPath('data.period.start', '2026-11-01')
            ->assertJsonPath('data.period.end', '2026-11-30')
            ->assertJsonPath('data.period.next_start', '2026-12-01')
            ->assertJsonCount(30, 'data.buckets')
            ->assertJsonPath('data.buckets.2.completed', 1)
            ->assertJsonPath('data.totals.completed', 1)
            ->assertJsonCount(1, 'data.shorter')
            ->assertJsonPath('data.shorter.0.wins', 'A strong first week');
    }

    public function test_a_year_has_a_bar_per_month_and_its_monthly_reviews(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach (['2026-02-10 09:00:00', '2026-11-03 09:00:00', '2026-11-04 09:00:00'] as $at) {
            Task::factory()->for($user)->create(['status' => TaskStatus::Completed])->forceFill(['completed_at' => $at])->save();
        }
        $this->putJson('/api/v1/reviews/month/2026-10-15', ['wins' => 'Launched', 'completed' => true])->assertOk();

        $this->getJson('/api/v1/reviews/year/2026-11-06')
            ->assertOk()
            ->assertJsonPath('data.period.label', '2026')
            ->assertJsonPath('data.period.start', '2026-01-01')
            ->assertJsonCount(12, 'data.buckets')
            ->assertJsonPath('data.buckets.1.completed', 1)
            ->assertJsonPath('data.buckets.10.completed', 2)
            ->assertJsonPath('data.buckets.10.end', '2026-11-30')
            ->assertJsonPath('data.totals.completed', 3)
            ->assertJsonPath('data.shorter.0.label', 'October 2026');

        $this->getJson('/api/v1/reviews?period=month')->assertJsonCount(1, 'data')->assertJsonPath('data.0.start', '2026-10-01');
    }

    public function test_each_period_keeps_its_own_reflection(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/api/v1/reviews/week/2026-11-02', ['wins' => 'Week'])->assertOk();
        $this->putJson('/api/v1/reviews/month/2026-11-02', ['wins' => 'Month'])->assertOk();
        $this->putJson('/api/v1/reviews/year/2026-11-02', ['wins' => 'Year'])->assertOk();

        $this->assertSame(3, Review::count());
        $this->getJson('/api/v1/reviews/week/2026-11-02')->assertJsonPath('data.review.wins', 'Week');
        $this->getJson('/api/v1/reviews/month/2026-11-02')->assertJsonPath('data.review.wins', 'Month');
        $this->getJson('/api/v1/reviews/year/2026-11-02')->assertJsonPath('data.review.wins', 'Year');
    }

    public function test_the_reflection_is_saved_and_marked_done(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->putJson('/api/v1/reviews/week/2026-11-06', ['wins' => ' Shipped ', 'challenges' => '', 'next_focus' => 'Rest'])
            ->assertOk()
            ->assertJsonPath('data.review.wins', 'Shipped')
            ->assertJsonPath('data.review.challenges', null)
            ->assertJsonPath('data.review.completed_at', null);

        $this->putJson('/api/v1/reviews/week/2026-11-02', ['completed' => true])->assertOk()->assertJsonPath('data.review.next_focus', 'Rest');

        $this->assertSame(1, Review::count());
        $this->getJson('/api/v1/reviews')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.period', 'week')
            ->assertJsonPath('data.0.start', '2026-11-02')
            ->assertJsonPath('data.0.label', 'Week of Nov 2, 2026');
    }

    public function test_future_periods_and_bad_addresses_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/reviews/week/2026-11-09')->assertNotFound();
        $this->getJson('/api/v1/reviews/month/2026-12-01')->assertNotFound();
        $this->getJson('/api/v1/reviews/year/2027-01-01')->assertNotFound();
        $this->getJson('/api/v1/reviews/month/2026-11-30')->assertOk();
        $this->getJson('/api/v1/reviews/decade/2026-11-02')->assertNotFound();
        $this->putJson('/api/v1/reviews/week/2026-02-30', [])->assertNotFound();
        $this->getJson('/api/v1/reviews/garbage')->assertNotFound();
        $this->putJson('/api/v1/reviews/2026-1-5', [])->assertNotFound();
        $this->getJson('/api/v1/reviews?period=decade')->assertUnprocessable();
    }

    public function test_reviews_are_private(): void
    {
        $other = User::factory()->create();
        $other->reviews()->create(['starts_on' => '2026-11-02', 'wins' => 'Secret', 'completed_at' => now()]);
        $other->reviews()->create(['period' => 'month', 'starts_on' => '2026-11-01', 'wins' => 'Secret month', 'completed_at' => now()]);
        $this->actingAs(User::factory()->create());

        $this->getJson('/api/v1/reviews/week/2026-11-02')->assertJsonPath('data.review', null);
        $this->getJson('/api/v1/reviews/month/2026-11-02')->assertJsonPath('data.review', null)->assertJsonPath('data.shorter', []);
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
        $this->putJson('/api/v1/reviews/month/2026-11-02', ['completed' => true])->assertOk();
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.review.done', false);
        $this->putJson('/api/v1/reviews/week/2026-11-02', ['completed' => true])->assertOk();
        $this->getJson('/api/v1/dashboard')->assertJsonPath('data.review.done', true);
    }

    public function test_guests_are_rejected(): void
    {
        $this->getJson('/api/v1/reviews/week/2026-11-02')->assertUnauthorized();
    }
}
