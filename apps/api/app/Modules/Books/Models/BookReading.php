<?php

namespace App\Modules\Books\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One sitting with a book: the day, the chapters it covered and, optionally, how long it took.
 * Adding, changing or removing one moves the book's progress by the same number of chapters.
 */
#[Fillable(['read_on', 'chapters', 'minutes', 'note'])]
class BookReading extends Model
{
    protected $attributes = [
        'chapters' => 0,
    ];

    protected static function booted(): void
    {
        static::created(fn (BookReading $reading) => $reading->book->advance($reading->chapters, $reading->read_on->toDateString()));
        static::updated(function (BookReading $reading) {
            $change = $reading->chapters - (int) $reading->getOriginal('chapters');
            if ($change !== 0) {
                $reading->book->advance($change, $reading->read_on->toDateString());
            }
        });
        static::deleted(fn (BookReading $reading) => $reading->book->advance(-$reading->chapters, $reading->read_on->toDateString()));
    }

    protected function casts(): array
    {
        return [
            'read_on' => 'date',
            'chapters' => 'integer',
            'minutes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Book, $this>
     */
    public function book(): BelongsTo
    {
        return $this->belongsTo(Book::class);
    }
}
