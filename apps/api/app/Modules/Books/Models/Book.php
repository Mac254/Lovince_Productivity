<?php

namespace App\Modules\Books\Models;

use App\Modules\Books\Database\Factories\BookFactory;
use App\Modules\Books\Enums\BookStatus;
use App\Modules\Goals\Models\Goal;
use App\Modules\Tasks\Models\Task;
use App\Modules\Users\Models\User;
use App\Shared\Support\ActivityObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(ActivityObserver::class)]
#[Fillable(['goal_id', 'title', 'author', 'status', 'chapters_total', 'chapters_read', 'started_on', 'finished_on', 'rating', 'notes'])]
#[UseFactory(BookFactory::class)]
class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory;

    public const RELATIONS = ['goal:id,title,tone'];

    protected $attributes = [
        'status' => 'to_find',
        'chapters_read' => 0,
    ];

    protected static function booted(): void
    {
        static::saving(function (Book $book) {
            if ($book->chapters_total !== null) {
                $book->chapters_read = min($book->chapters_read, $book->chapters_total);
            }

            if ($book->status === BookStatus::Finished) {
                $book->finished_on ??= today();
                $book->started_on ??= $book->finished_on;
                if ($book->chapters_total !== null) {
                    $book->chapters_read = $book->chapters_total;
                }
            } else {
                $book->finished_on = null;
            }

            if ($book->status === BookStatus::Reading) {
                $book->started_on ??= today();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => BookStatus::class,
            'chapters_total' => 'integer',
            'chapters_read' => 'integer',
            'rating' => 'integer',
            'started_on' => 'date',
            'finished_on' => 'date',
        ];
    }

    /**
     * Moves the book on by a change in chapters read on a day: a first reading starts it, reaching
     * the last chapter finishes it, and taking chapters back off a finished book reopens it.
     */
    public function advance(int $chapters, string $on): void
    {
        $this->chapters_read = max(0, $this->chapters_read + $chapters);

        if ($chapters > 0 && $this->status->isWaiting()) {
            $this->status = BookStatus::Reading;
        }
        if ($chapters > 0 && ($this->started_on === null || $this->started_on->toDateString() > $on)) {
            $this->started_on = $on;
        }

        $complete = $this->chapters_total !== null && $this->chapters_read >= $this->chapters_total;
        if ($complete && $chapters > 0 && $this->status !== BookStatus::Finished) {
            $this->status = BookStatus::Finished;
            $this->finished_on = $on;
        } elseif (! $complete && $this->status === BookStatus::Finished && $this->chapters_total !== null) {
            $this->status = BookStatus::Reading;
        }

        $this->save();
    }

    public function progress(): ?int
    {
        if ($this->status === BookStatus::Finished) {
            return 100;
        }
        if (! $this->chapters_total) {
            return null;
        }

        return (int) min(100, round($this->chapters_read / $this->chapters_total * 100));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Goal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * @return HasMany<BookReading, $this>
     */
    public function readings(): HasMany
    {
        return $this->hasMany(BookReading::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}
