<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\LineKind;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kind', 'account_id', 'category_id', 'debit', 'credit', 'currency'])]
#[Table('accounting_journal_lines')]
class JournalLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'kind' => LineKind::class,
            'debit' => 'integer',
            'credit' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
