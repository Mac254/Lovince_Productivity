<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\Frequency;
use App\Modules\Accounting\Enums\TransactionDirection;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Accounting\Enums\TransactionType;
use App\Modules\Users\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A payment that repeats, such as rent or a salary. On each due date it records an ordinary
 * transaction, posted or left pending to confirm, so balances and reports need nothing extra.
 */
#[Fillable([
    'account_id', 'to_account_id', 'category_id', 'type', 'direction', 'status', 'amount', 'currency',
    'description', 'notes', 'reference', 'frequency', 'start_date', 'end_date', 'paused_at',
])]
#[Table('accounting_recurring_transactions')]
class RecurringTransaction extends Model
{
    /** The kinds of money that repeat; refunds and adjustments are one-offs. */
    public const TYPES = [TransactionType::Income, TransactionType::Expense, TransactionType::Transfer, TransactionType::Interest, TransactionType::Fee];

    public const RELATIONS = ['account:id,name,type,currency,archived_at', 'toAccount:id,name,type,currency,archived_at', 'category:id,name,kind'];

    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'direction' => TransactionDirection::class,
            'status' => TransactionStatus::class,
            'frequency' => Frequency::class,
            'amount' => 'integer',
            'occurrences' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'next_date' => 'date',
            'paused_at' => 'datetime',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Sets the next due date from the schedule: the first payment after `$after` (the last one
     * recorded), or the first payment itself. Null once the schedule has ended.
     */
    public function plan(?CarbonInterface $after = null): void
    {
        $this->occurrences = 0;
        while ($after !== null && $this->frequency->nth($this->start_date, $this->occurrences)->lte($after)) {
            $this->occurrences++;
        }
        $this->setNext();
    }

    /**
     * Moves past the payment just recorded.
     */
    public function advance(): void
    {
        $this->occurrences++;
        $this->setNext();
    }

    /**
     * The transaction to record for the payment due on `$date`.
     *
     * @return array<string, mixed>
     */
    public function transactionFor(string $date): array
    {
        return [
            ...$this->only(['type', 'direction', 'status', 'account_id', 'to_account_id', 'category_id', 'amount', 'currency', 'description', 'notes', 'reference']),
            'date' => $date,
            'recurring_transaction_id' => $this->id,
        ];
    }

    private function setNext(): void
    {
        $next = $this->frequency->nth($this->start_date, $this->occurrences);
        $this->next_date = $this->end_date !== null && $next->gt($this->end_date) ? null : $next;
    }
}
