<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Enums\GoalKind;
use App\Modules\Accounting\Models\FinancialGoal;
use App\Modules\Accounting\Models\MoneyRule;
use App\Modules\Accounting\Support\Money;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rules worth keeping, worked out from the user's open goals and recent spending in their main
 * currency. Spending is averaged over the calendar months since tracking began, at most the last
 * three, this month included. A suggestion the user already added (archived or not) is left out.
 */
final class RuleSuggestions
{
    private const MONTHS = 3;

    private const LIMIT = 6;

    private string $currency = '';

    public function __construct(private Statement $statement, private GoalProgress $progress) {}

    /**
     * @return list<array{key: string, title: string, reason: string, source: 'goals'|'spending'}>
     */
    public function for(User $user): array
    {
        $this->currency = Overview::currencyFor($user, null);
        $today = today();
        $from = $this->windowStart($user, $today);
        $months = max(1, ($today->year - $from->year) * 12 + $today->month - $from->month + 1);
        $since = $from->format('F');

        $statement = $this->statement->incomeStatement($user, $from, $today, $this->currency);
        $income = intdiv($statement['income']['total'], $months);
        $spent = intdiv($statement['expenses']['total'], $months);

        $goals = $this->progress->measure(
            FinancialGoal::query()->whereBelongsTo($user)->whereNull('archived_at')->where('currency', $this->currency)
                ->orderByRaw('CASE WHEN target_date IS NULL THEN 1 ELSE 0 END')->orderBy('target_date')->orderBy('name')->get()
        )->filter(fn (FinancialGoal $goal) => $goal->saved < $goal->target_amount)->values();

        $suggestions = [];
        $dated = $goals->map(fn (FinancialGoal $goal) => [$goal, GoalProgress::monthlyNeeded($goal)])->filter(fn ($pair) => $pair[1] !== null);
        $needed = (int) $dated->sum(fn ($pair) => $pair[1]);

        if ($needed > 0) {
            $parts = $dated->map(fn ($pair) => "{$pair[0]->name} needs {$this->money($pair[1])}")->implode(', ');
            $share = $income > 0 ? ' That is about '.(int) round($needed * 100 / $income).'% of what comes in each month.' : '';
            $suggestions[] = $this->goal('pay-goals-first', "On payday, move {$this->money($needed)} to my goals before spending", "{$parts} a month to stay on time.{$share}");
        }

        $emergency = $goals->first(fn (FinancialGoal $goal) => $goal->kind === GoalKind::EmergencyFund);
        if ($emergency !== null) {
            $suggestions[] = $this->goal(
                'emergency-fund-first',
                'Finish the emergency fund before investing more',
                "{$emergency->name} is at {$this->money($emergency->saved)} of {$this->money($emergency->target_amount)}. Until it is full, spare money goes there first.",
            );
        }

        foreach ($goals->whereNull('target_date') as $goal) {
            $suggestions[] = $this->goal(
                "goal-{$goal->id}",
                "Add something to {$goal->name} every month, however small",
                "It is at {$this->money($goal->saved)} of {$this->money($goal->target_amount)} and has no date, so a monthly habit keeps it moving.",
            );
        }

        if ($income > 0 && $spent > $income) {
            $suggestions[] = $this->spending(
                'spend-less-than-earn',
                'Spend less than I earn every month',
                "Since {$since} you have spent about {$this->money($spent)} a month against {$this->money($income)} coming in.",
            );
        }

        if ($spent > 0) {
            $cap = $this->roundUp($spent, 1000);
            $share = $income > 0 ? ', '.(int) round($spent * 100 / $income).'% of your income' : '';
            $suggestions[] = $this->spending(
                'monthly-spending-cap',
                "Keep monthly spending under {$this->money($cap)}",
                "Since {$since} you have spent about {$this->money($spent)} a month{$share}.",
            );

            $total = $statement['expenses']['total'];
            foreach (array_slice($statement['expenses']['categories'], 0, 2) as $category) {
                if ($category['id'] === null || $total > $category['amount'] * 10) {
                    continue;
                }
                $average = intdiv($category['amount'], $months);
                $suggestions[] = $this->spending(
                    "category-{$category['id']}",
                    "Keep {$category['name']} under {$this->money($this->roundUp($average, 500))} a month",
                    'It is '.(int) round($category['amount'] * 100 / $total)."% of your spending, about {$this->money($average)} a month.",
                );
            }
        }

        $left = $income - $spent - $needed;
        if ($emergency === null && $income > 0 && $left > 0) {
            $suggestions[] = $this->goal(
                'invest-what-is-left',
                'Invest what is left after goals and spending',
                "About {$this->money($left)} a month is left once spending and goal money are covered.",
            );
        }

        $taken = MoneyRule::query()->whereBelongsTo($user)->whereNotNull('suggestion')->pluck('suggestion')->flip();

        return array_values(array_slice(array_filter($suggestions, fn (array $s) => ! isset($taken[$s['key']])), 0, self::LIMIT));
    }

    private function windowStart(User $user, Carbon $today): Carbon
    {
        $earliest = $today->copy()->startOfMonth()->subMonthsNoOverflow(self::MONTHS - 1);
        $first = DB::table('accounting_journal_entries')->where('user_id', $user->id)->min('date');

        return $first === null ? $today->copy()->startOfMonth() : Carbon::parse($first)->startOfMonth()->max($earliest);
    }

    /**
     * @return array{key: string, title: string, reason: string, source: 'goals'}
     */
    private function goal(string $key, string $title, string $reason): array
    {
        return ['key' => $key, 'title' => $title, 'reason' => $reason, 'source' => 'goals'];
    }

    /**
     * @return array{key: string, title: string, reason: string, source: 'spending'}
     */
    private function spending(string $key, string $title, string $reason): array
    {
        return ['key' => $key, 'title' => $title, 'reason' => $reason, 'source' => 'spending'];
    }

    /**
     * Rounds up to a whole number of `$step` major units.
     */
    private function roundUp(int $minor, int $step): int
    {
        $unit = $step * 10 ** Money::decimals($this->currency);

        return (int) ceil($minor / $unit) * $unit;
    }

    private function money(int $minor): string
    {
        return $this->currency.' '.number_format(round($minor / 10 ** Money::decimals($this->currency)));
    }
}
