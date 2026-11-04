<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * What the user records: an income, expense or transfer and its status. Posting it writes a
 * balanced journal entry; later corrections add entries and never change or delete earlier ones.
 */
#[Fillable([
    'account_id', 'to_account_id', 'category_id', 'type', 'direction', 'status', 'amount', 'currency',
    'date', 'description', 'notes', 'reference', 'reversal_of_id', 'idempotency_key', 'posted_at', 'voided_at',
])]
#[Table('accounting_transactions')]
class Transaction extends Model
{
    public const RELATIONS = ['account:id,name,type,currency', 'toAccount:id,name,type,currency', 'category:id,name,kind', 'reversal:id,reversal_of_id'];

    /** The fields whose change alters the ledger. */
    public const LEDGER_FIELDS = ['type', 'direction', 'account_id', 'to_account_id', 'category_id', 'amount', 'currency', 'date'];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'status' => TransactionStatus::class,
            'amount' => 'integer',
            'date' => 'date',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
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
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    /**
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'to_account_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }

    /**
     * @return HasOne<self, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(self::class, 'reversal_of_id');
    }

    /**
     * @return HasMany<JournalEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    /**
     * A reversal, or a transaction that has one, is settled history and no longer changes.
     */
    public function isLocked(): bool
    {
        return $this->reversal_of_id !== null || $this->reversal()->exists();
    }
}
