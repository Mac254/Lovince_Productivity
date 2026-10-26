<?php

namespace Tests\Feature;

use App\Models\JournalEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
