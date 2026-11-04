<?php

namespace App\Modules\Accounting\Console\Commands;

use App\Modules\Accounting\Actions\PostRecurringTransactions;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Users\Services\AccountTimezone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounting:post-recurring')]
#[Description('Record the recurring payments that have come due, each on its owner\'s today')]
class PostRecurring extends Command
{
    public function handle(PostRecurringTransactions $post): int
    {
        $count = 0;
        RecurringTransaction::query()
            ->whereNull('paused_at')
            ->whereNotNull('next_date')
            // A day ahead covers owners whose today is already tomorrow here; the action checks exactly.
            ->whereDate('next_date', '<=', today()->addDay()->toDateString())
            ->with('user')
            ->lazyById()
            ->each(function (RecurringTransaction $rule) use ($post, &$count) {
                $count += AccountTimezone::run($rule->user, fn () => $post->handle($rule));
            });

        $this->components->info("Recorded {$count} recurring ".str('payment')->plural($count).'.');

        return self::SUCCESS;
    }
}
