<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How much the user means to spend in a spending category each month, in one currency.
 *
 * @property int|null $spent Spending in the month being shown; set by the controller, not stored.
 */
#[Fillable(['category_id', 'amount', 'currency'])]
#[Table('accounting_budgets')]
class Budget extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
