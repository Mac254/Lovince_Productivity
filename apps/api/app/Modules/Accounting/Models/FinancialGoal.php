<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Accounting\Enums\GoalKind;
use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Money the user is putting aside for something: savings, an emergency fund, an investment.
 * What has been saved is never stored; `GoalProgress` reads it from the linked account's
 * balance or, without an account, from the contributions the user records.
 *
 * @property int|null $saved
 */
#[Fillable(['account_id', 'name', 'kind', 'target_amount', 'currency', 'target_date', 'archived_at'])]
#[Table('accounting_goals')]
class FinancialGoal extends Model
{
    protected function casts(): array
    {
        return [
            'kind' => GoalKind::class,
            'target_amount' => 'integer',
            'target_date' => 'date',
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
     * @return BelongsTo<FinancialAccount, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'account_id');
    }

    /**
     * @return HasMany<GoalContribution, $this>
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class, 'goal_id');
    }
}
