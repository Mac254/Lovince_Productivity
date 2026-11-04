<?php

namespace App\Modules\Accounting\Tests\Feature;

use Illuminate\Support\Carbon;

/**
 * Drives a month of money through the API (income, spending, transfers, pending, void, reverse, refund, a budget
 * and a recurring payment) and checks that balances, the overview, statements, the trend and budgets agree.
 */
class AccountingDataFlowTest extends AccountingTestCase
{
    /** @var list<string> */
    private array $mismatches = [];

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_every_money_summary_agrees_with_a_simulated_month(): void
    {
        $this->user->forceFill(['timezone' => 'Africa/Nairobi'])->save();
        Carbon::setTestNow(Carbon::parse('2026-11-20 09:00', 'Africa/Nairobi'));

        $bank = $this->account(['name' => 'Bank', 'currency' => 'KES', 'opening_balance' => 5_000_000]);
        $mpesa = $this->account(['name' => 'M-Pesa', 'type' => 'mobile_money', 'currency' => 'KES', 'opening_balance' => 200_000]);
        $savings = $this->account(['name' => 'Savings', 'type' => 'savings', 'currency' => 'KES', 'opening_balance' => 0]);
        $salary = $this->category('income', 'Salary');
        $food = $this->category('expense', 'Food');
        $transport = $this->category('expense', 'Transport');
        $housing = $this->category('expense', 'Housing');

        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '10000'])->assertCreated();
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $bank->id, 'category_id' => $housing->id, 'amount' => '20000',
            'description' => 'Rent', 'frequency' => 'monthly', 'start_date' => '2026-11-01',
        ])->assertCreated();

        $move = fn (array $input, string $date) => $this->record($input + ['date' => $date])->assertCreated()->json('data.id');
        $move(['type' => 'expense', 'account_id' => $mpesa->id, 'category_id' => $food->id, 'amount' => '1200'], '2026-11-03');
        $move(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '4300.50'], '2026-11-08');
        $move(['type' => 'refund', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '500'], '2026-11-09');
        $move(['type' => 'expense', 'account_id' => $mpesa->id, 'category_id' => $transport->id, 'amount' => '650'], '2026-11-10');
        $move(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $savings->id, 'amount' => '10000'], '2026-11-05');
        $move(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $mpesa->id, 'amount' => '5000'], '2026-11-06');
        $move(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '9000'], '2026-10-28');
        $move(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '1000', 'status' => 'pending'], '2026-11-12');
        $voided = $move(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '777'], '2026-11-11');
        $this->postJson("/api/v1/accounting/transactions/{$voided}/void")->assertSuccessful();
        $reversed = $move(['type' => 'expense', 'account_id' => $mpesa->id, 'category_id' => $transport->id, 'amount' => '300'], '2026-11-13');
        $this->postJson("/api/v1/accounting/transactions/{$reversed}/reverse")->assertSuccessful();
        $this->record(['type' => 'income', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => '80000', 'date' => '2026-11-01'], 'salary-once')->assertCreated();
        $this->record(['type' => 'income', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => '80000', 'date' => '2026-11-01'], 'salary-once');

        $this->artisan('accounting:post-recurring')->assertSuccessful();
        $this->artisan('accounting:post-recurring')->assertSuccessful();

        $income = 8_000_000;
        $foodSpent = 120_000 + 430_050 - 50_000;
        $expenses = $foodSpent + 65_000 + 2_000_000;
        $balances = [
            $bank->id => 5_000_000 + 8_000_000 + 50_000 - 430_050 - 1_000_000 - 500_000 - 900_000 - 2_000_000,
            $mpesa->id => 200_000 - 120_000 - 65_000 + 500_000,
            $savings->id => 1_000_000,
        ];

        $accounts = collect($this->getJson('/api/v1/accounting/accounts')->assertOk()->json('data'))->keyBy('id');
        foreach ($balances as $id => $balance) {
            $this->same("balance of {$accounts[$id]['name']}", $balance, $accounts[$id]['balance']);
        }
        $this->same('bank available holds back the pending payment', $balances[$bank->id] - 100_000, $accounts[$bank->id]['available_balance']);

        $period = 'from=2026-11-01&to=2026-11-30&currency=KES';
        $overview = $this->getJson("/api/v1/accounting/dashboard?{$period}")->assertOk()->json('data');
        $this->same('overview balance', array_sum($balances), $overview['balance']);
        $this->same('overview available', array_sum($balances) - 100_000, $overview['available_balance']);
        $this->same('overview income', $income, $overview['income']);
        $this->same('overview expenses', $expenses, $overview['expenses']);
        $this->same('overview net', $income - $expenses, $overview['net_cash_flow']);
        $this->same('overview previous expenses', 900_000, $overview['previous_totals']['expenses']);
        $this->same('overview pending', ['count' => 1, 'total' => 100_000], $overview['pending']);
        $this->same('top spending adds up', $expenses, array_sum(array_column($overview['top_expenses'], 'amount')));
        $this->same('overview budget spent', $foodSpent, $overview['budgets']['spent']);
        $this->same('overview upcoming rent', ['Rent' => '2026-12-01'], collect($overview['upcoming'])->pluck('next_date', 'description')->all());

        $trend = collect($overview['trend'])->keyBy('month');
        $this->same('trend income', $income, $trend['2026-11']['income']);
        $this->same('trend expenses', $expenses, $trend['2026-11']['expenses']);
        $this->same('trend last month', 900_000, $trend['2026-10']['expenses']);

        $statement = $this->getJson("/api/v1/accounting/reports/income-statement?{$period}")->assertOk()->json('data');
        $this->same('statement income', $income, $statement['income']['total']);
        $this->same('statement expenses', $expenses, $statement['expenses']['total']);
        $this->same('statement net', $income - $expenses, $statement['net']);
        $this->same('statement food', $foodSpent, collect($statement['expenses']['categories'])->firstWhere('id', $food->id)['amount'] ?? null);

        $cashFlow = $this->getJson("/api/v1/accounting/reports/cash-flow?{$period}")->assertOk()->json('data');
        $this->same('cash flow net', $income - $expenses, $cashFlow['net']);
        $this->same('cash flow closing', array_sum($balances), $cashFlow['closing']);
        foreach ($cashFlow['accounts'] as $row) {
            $this->same("cash flow closing of {$row['name']}", $balances[$row['id']], $row['closing']);
        }

        $reportTrend = collect($this->getJson('/api/v1/accounting/reports/trend?currency=KES')->assertOk()->json('data.months'))->keyBy('month');
        $this->same('report trend matches the overview', $trend['2026-11'], $reportTrend['2026-11'] ?? null);

        $budget = $this->getJson('/api/v1/accounting/budgets?month=2026-11')->assertOk()->json('data.0');
        $this->same('budget spent', $foodSpent, $budget['spent']);
        $this->same('budget remaining', 1_000_000 - $foodSpent, $budget['remaining']);

        $rule = $this->getJson('/api/v1/accounting/recurring')->assertOk()->json('data.0');
        $this->same('rent posted once', 1, $rule['occurrences']);
        $this->same('rent next due', '2026-12-01', $rule['next_date']);
        $posted = collect($this->getJson('/api/v1/accounting/transactions?per_page=100')->json('data'));
        $this->same('rent transaction', 1, $posted->where('recurring_transaction_id', $rule['id'])->count());
        $this->same('repeated key records once', 1, $posted->where('type', 'income')->count());

        $this->assertSame([], $this->mismatches);
    }

    private function same(string $what, mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            $this->mismatches[] = "{$what}: expected ".json_encode($expected).', got '.json_encode($actual);
        }
    }
}
