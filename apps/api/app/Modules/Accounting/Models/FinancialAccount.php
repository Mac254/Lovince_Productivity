<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Database\Factories\FinancialAccountFactory;
use App\Modules\Accounting\Enums\AccountType;
use App\Modules\Accounting\Enums\TransactionStatus;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A place the user keeps money: cash, a bank or M-Pesa account, a SACCO, a card. Its balance is
 * never stored; `Ledger::balances()` computes it from the opening balance and the journal.
 *
 * @property int|null $balance
 * @property int|null $available_balance
 * @property int|null $total_inflows
 * @property int|null $total_outflows
 */
#[Fillable(['name', 'type', 'currency', 'opening_balance', 'archived_at'])]
#[Table('accounting_accounts')]
#[UseFactory(FinancialAccountFactory::class)]
class FinancialAccount extends Model
{
    /** @use HasFactory<FinancialAccountFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'opening_balance' => 'integer',
            'archived_at' => 'datetime',
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
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'account_id');
    }

    public function hasActivity(): bool
    {
        return Transaction::query()
            ->where(fn ($q) => $q->where('account_id', $this->id)->orWhere('to_account_id', $this->id))
            ->exists();
    }

    public function hasReconciled(): bool
    {
        return Transaction::query()
            ->where(fn ($q) => $q->where('account_id', $this->id)->orWhere('to_account_id', $this->id))
            ->where('status', TransactionStatus::Reconciled)
            ->exists();
    }
}
