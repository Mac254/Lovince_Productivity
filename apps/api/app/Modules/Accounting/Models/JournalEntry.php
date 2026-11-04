<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * One balanced posting: its lines' debits equal their credits. Entries are never edited; a
 * correction adds an entry that `reverses` the old one on the same date.
 */
#[Fillable(['user_id', 'transaction_id', 'reverses_id', 'date'])]
#[Table('accounting_journal_entries')]
class JournalEntry extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * @return HasMany<JournalLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_id');
    }

    /**
     * Entries still in force: neither a correction nor corrected.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEffective(Builder $query): void
    {
        $query->whereNull('reverses_id')->whereDoesntHave('reversedBy');
    }
}
