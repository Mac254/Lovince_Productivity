<?php

namespace App\Modules\Accounting\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Money put towards a goal that has no account of its own; a negative amount is money taken out.
 */
#[Fillable(['amount', 'date', 'note'])]
#[Table('accounting_goal_contributions')]
class GoalContribution extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer', 'date' => 'date'];
    }

    /**
     * @return BelongsTo<FinancialGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class, 'goal_id');
    }
}
