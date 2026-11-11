<?php

namespace App\Modules\Accounting\Actions;

use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Services\AuditTrail;
use Illuminate\Support\Facades\DB;

/**
 * Records every payment of a recurring rule that has come due, including any missed while the
 * app was off. Each payment has its own idempotency key, so running twice records nothing twice.
 * Run inside the owner's timezone, so "due" means on or before their today.
 */
final class PostRecurringTransactions
{
    /** At most this many payments per run, so a rule started long ago can't flood the ledger at once. */
    private const LIMIT = 60;

    public function __construct(private RecordTransaction $record) {}

    /**
     * @return int How many payments were recorded.
     */
    public function handle(RecurringTransaction $rule): int
    {
        $rule->loadMissing(['user', 'account', 'toAccount']);
        $today = today()->toDateString();
        $count = 0;

        while ($rule->paused_at === null && $rule->next_date !== null && $rule->next_date->toDateString() <= $today && $count < self::LIMIT) {
            if ($rule->account->archived_at !== null || $rule->toAccount?->archived_at !== null) {
                $rule->forceFill(['paused_at' => now()])->save();
                AuditTrail::record($rule->user, 'recurring.paused', $rule, ['reason' => 'account archived']);
                break;
            }
            if ($rule->currency !== $rule->account->currency) {
                $rule->forceFill(['paused_at' => now()])->save();
                AuditTrail::record($rule->user, 'recurring.paused', $rule, ['reason' => 'currency changed']);
                break;
            }

            DB::transaction(function () use ($rule) {
                $date = $rule->next_date->toDateString();
                $this->record->handle($rule->user, $rule->transactionFor($date), "recurring-{$rule->id}-{$date}");
                $rule->advance();
                $rule->save();
            });
            $count++;
        }

        return $count;
    }
}
