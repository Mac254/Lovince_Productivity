<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JournalTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_entry_per_day_is_created_updated_and_cleared(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $today = today()->toDateString();

        $this->putJson("/api/v1/journal/{$today}", ['body' => 'Shipped the redesign.'])->assertOk()->assertJsonPath('data.date', $today);
        $this->putJson("/api/v1/journal/{$today}", ['body' => 'Shipped the redesign. Integration next week.'])->assertOk();

        $this->assertSame(1, JournalEntry::count());
        $this->getJson("/api/v1/journal/{$today}")->assertJsonPath('data.body', 'Shipped the redesign. Integration next week.');

        $this->putJson("/api/v1/journal/{$today}", ['body' => '   '])->assertOk()->assertExactJson(['data' => null]);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_future_and_invalid_dates_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/v1/journal/'.today()->addDay()->toDateString(), ['body' => 'x'])->assertNotFound();
        $this->putJson('/api/v1/journal/2026-02-30', ['body' => 'x'])->assertNotFound();
    }

    public function test_entries_are_private(): void
    {
        $other = User::factory()->create();
        $other->journalEntries()->create(['date' => today(), 'body' => 'Private thoughts']);

        $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/journal/'.today()->toDateString())
            ->assertExactJson(['data' => null]);
        $this->getJson('/api/v1/journal')->assertJsonCount(0, 'data');
    }

    public function test_entries_can_be_listed_for_a_calendar_range(): void
    {
        Carbon::setTestNow('2026-11-05 12:00:00');
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach (['2026-09-30', '2026-10-01', '2026-10-15', '2026-10-31', '2026-11-01'] as $day) {
            $this->putJson("/api/v1/journal/{$day}", ['body' => "Note for {$day}"])->assertOk();
        }

        $this->getJson('/api/v1/journal?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.date', '2026-10-31');

        $this->getJson('/api/v1/journal?from=2026-10-01')->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/journal?from=2026-10-31&to=2026-10-01')->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/journal?from=2026-01-01&to=2026-12-31')->assertJsonValidationErrors('to');
    }
}
