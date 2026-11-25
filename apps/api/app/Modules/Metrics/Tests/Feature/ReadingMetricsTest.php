<?php

namespace App\Modules\Metrics\Tests\Feature;

use App\Modules\Books\Enums\BookKind;
use App\Modules\Books\Models\Book;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReadingMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_podcast_listening_is_kept_out_of_reading_figures(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['kind' => BookKind::Book]);
        $show = Book::factory()->for($user)->create(['kind' => BookKind::Podcast]);
        $today = today()->toDateString();
        $this->actingAs($user);
        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => $today, 'chapters' => 1, 'minutes' => 20])->assertCreated();
        $this->postJson("/api/v1/books/{$show->id}/readings", ['read_on' => $today, 'minutes' => 60, 'episode' => 'Ep 1'])->assertCreated();

        $this->getJson('/api/v1/metrics?days=7')
            ->assertOk()
            ->assertJsonPath('data.reading.minutes', 20)
            ->assertJsonPath('data.reading.sessions', 1)
            ->assertJsonPath('data.listening.episodes', 1)
            ->assertJsonPath('data.listening.minutes', 60)
            ->assertJsonPath('data.listening.shows.0.id', $show->id)
            ->assertJsonPath('data.habits.planned', 0);
    }

    public function test_reading_logged_by_time_counts_as_reading(): void
    {
        $user = User::factory()->create();
        $book = Book::factory()->for($user)->create(['kind' => BookKind::Book, 'title' => 'The Almanack']);
        $book->forceFill(['status' => 'reading'])->save();
        $this->actingAs($user);
        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => today()->toDateString(), 'minutes' => 40, 'note' => 'To page 31'])->assertCreated();

        $insight = collect($this->getJson('/api/v1/metrics?days=1')->assertOk()->json('data.insights'))->firstWhere('key', 'reading');

        $this->assertSame('You read for 40 min today.', $insight['text'] ?? null);
    }
}
