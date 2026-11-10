<?php

namespace App\Modules\Accounting\Models;

use App\Modules\Users\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A money rule the user keeps in sight, such as "pay the emergency fund first", with why it
 * matters. Rules are reminders and are never checked against the ledger. `suggestion` keeps
 * the key of the suggestion a rule was added from, so that suggestion is not offered again.
 */
#[Fillable(['title', 'reason', 'pinned', 'position', 'suggestion', 'archived_at'])]
#[Table('accounting_rules')]
class MoneyRule extends Model
{
    protected function casts(): array
    {
        return [
            'pinned' => 'boolean',
            'position' => 'integer',
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
}
