<?php

namespace App\Modules\Books\Tests\Feature;

use App\Modules\Books\Models\Book;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class BookTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_user_can_add_a_book_to_find(): void
    {
        $this->postJson('/api/v1/books', ['title' => '  30 Lessons for Living  ', 'author' => 'Karl Pillemer'])
            ->assertCreated()
            ->assertJsonPath('data.title', '30 Lessons for Living')
            ->assertJsonPath('data.status', 'to_find')
            ->assertJsonPath('data.chapters_read', 0)
            ->assertJsonPath('data.progress', null)
            ->assertJsonPath('data.started_on', null);
    }

    public function test_finishing_a_book_dates_it_and_fills_its_chapters(): void
    {
        $book = Book::factory()->for($this->user)->create(['chapters_total' => 20, 'chapters_read' => 5, 'status' => 'reading', 'started_on' => '2026-11-01']);

        $this->patchJson("/api/v1/books/{$book->id}", ['status' => 'finished', 'finished_on' => '2026-11-08', 'rating' => 5])
            ->assertOk()
            ->assertJsonPath('data.status', 'finished')
            ->assertJsonPath('data.finished_on', '2026-11-08')
            ->assertJsonPath('data.chapters_read', 20)
            ->assertJsonPath('data.progress', 100);

        $this->patchJson("/api/v1/books/{$book->id}", ['status' => 'reading'])
            ->assertOk()
            ->assertJsonPath('data.finished_on', null);
    }

    public function test_reading_log_moves_progress_starts_and_finishes_the_book(): void
    {
        $book = Book::factory()->for($this->user)->create(['chapters_total' => 6, 'status' => 'to_read']);

        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => '2026-11-05', 'chapters' => 2, 'minutes' => 40])->assertCreated();
        $book->refresh();
        $this->assertSame('reading', $book->status->value);
        $this->assertSame('2026-11-05', $book->started_on->toDateString());
        $this->assertSame(2, $book->chapters_read);

        $last = $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => '2026-11-07', 'chapters' => 4])->assertCreated()->json('data.id');
        $book->refresh();
        $this->assertSame('finished', $book->status->value);
        $this->assertSame('2026-11-07', $book->finished_on->toDateString());

        $this->deleteJson("/api/v1/book-readings/{$last}")->assertNoContent();
        $book->refresh();
        $this->assertSame('reading', $book->status->value);
        $this->assertNull($book->finished_on);
        $this->assertSame(2, $book->chapters_read);

        $this->getJson("/api/v1/books/{$book->id}")
            ->assertOk()
            ->assertJsonPath('data.minutes_read', 40)
            ->assertJsonPath('data.last_read_on', '2026-11-05')
            ->assertJsonCount(1, 'data.readings');
    }

    public function test_an_empty_reading_is_rejected(): void
    {
        $book = Book::factory()->for($this->user)->create();

        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => '2026-11-05'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['chapters']);
    }

    public function test_reading_tasks_take_the_books_goal_and_can_be_linked_and_unlinked(): void
    {
        $goal = Goal::factory()->for($this->user)->create();
        $book = Book::factory()->for($this->user)->create(['goal_id' => $goal->id]);
        $existing = Task::factory()->for($this->user)->create();

        $created = $this->postJson("/api/v1/books/{$book->id}/tasks", ['title' => 'Read chapter 6', 'recurrence' => 'daily'])
            ->assertCreated()
            ->assertJsonPath('data.recurrence', 'daily')
            ->assertJsonPath('data.goal.id', $goal->id)
            ->json('data.id');
        $task = Task::find($created);
        $this->assertSame($book->id, $task->book_id);
        $this->assertSame($goal->id, $task->goal_id);

        $this->postJson("/api/v1/books/{$book->id}/tasks", ['task_id' => $existing->id])->assertCreated();
        $this->getJson("/api/v1/books/{$book->id}")->assertJsonCount(2, 'data.tasks')->assertJsonPath('data.open_tasks', 2);

        $this->deleteJson("/api/v1/books/{$book->id}/tasks/{$existing->id}")->assertNoContent();
        $this->assertNull($existing->fresh()->book_id);

        $book->delete();
        $this->assertNull($task->fresh()->book_id);
    }

    public function test_books_belong_to_their_owner(): void
    {
        $other = Book::factory()->create();
        $foreignTask = Task::factory()->create();
        $mine = Book::factory()->for($this->user)->create();

        $this->getJson("/api/v1/books/{$other->id}")->assertNotFound();
        $this->patchJson("/api/v1/books/{$other->id}", ['title' => 'Mine now'])->assertNotFound();
        $this->postJson("/api/v1/books/{$other->id}/readings", ['read_on' => '2026-11-05', 'chapters' => 1])->assertNotFound();
        $this->postJson("/api/v1/books/{$other->id}/tasks", ['title' => 'Read it'])->assertNotFound();
        $this->postJson("/api/v1/books/{$other->id}/tasks", [])->assertNotFound();
        $this->postJson("/api/v1/books/{$mine->id}/tasks", ['task_id' => $foreignTask->id])->assertUnprocessable();
        $this->getJson('/api/v1/books')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_reading_shows_in_reports_metrics_and_search(): void
    {
        $this->travelTo('2026-11-08 20:00:00');
        $book = Book::factory()->for($this->user)->create(['title' => 'The Psychology of Money', 'chapters_total' => 7, 'chapters_read' => 5, 'status' => 'reading']);
        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => '2026-11-08', 'chapters' => 2, 'minutes' => 45])->assertCreated();

        $this->postJson('/api/v1/reports')->assertSuccessful();
        $this->getJson('/api/v1/reports/2026-11-08')
            ->assertOk()
            ->assertJsonPath('data.summary.chapters_read', 2)
            ->assertJsonPath('data.summary.reading_minutes', 45)
            ->assertJsonPath('data.summary.books_finished', 1)
            ->assertJsonPath('data.reading.finished.0.title', 'The Psychology of Money');

        $this->getJson('/api/v1/metrics?days=7')
            ->assertOk()
            ->assertJsonPath('data.reading.chapters', 2)
            ->assertJsonPath('data.reading.finished', 1)
            ->assertJsonPath('data.reading.days', 1);

        $this->getJson('/api/v1/search?q=psychology')->assertOk()->assertJsonFragment(['kind' => 'book', 'title' => 'The Psychology of Money']);
    }

    public function test_books_survive_a_backup_round_trip_without_double_counting(): void
    {
        $book = Book::factory()->for($this->user)->create(['title' => 'Dune', 'author' => 'Frank Herbert', 'chapters_total' => 10, 'status' => 'to_read']);
        $task = Task::factory()->for($this->user)->create();
        $task->forceFill(['book_id' => $book->id])->save();
        $this->postJson("/api/v1/books/{$book->id}/readings", ['read_on' => '2026-11-07', 'chapters' => 3])->assertCreated();

        $backup = $this->getJson('/api/v1/backup')->assertOk()->json();
        $this->assertSame(3, $backup['books'][0]['chapters_read']);

        $other = User::factory()->create();
        $this->actingAs($other);
        Task::factory()->for($other)->create();
        $file = UploadedFile::fake()->createWithContent('backup.json', json_encode($backup));
        $this->postJson('/api/v1/backup', ['file' => $file])->assertOk()->assertJsonPath('data.books', 1);

        $imported = $other->books()->firstOrFail();
        $this->assertSame(3, $imported->chapters_read);
        $this->assertSame(1, $imported->readings()->count());
        $this->assertSame(1, $imported->tasks()->count());
    }
}
