<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\FinancialAccount;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A month of a household's books kept through the API, checked against figures worked out by
 * hand: the income statement, cash flow, every account balance and the trial balance.
 */
class BookkeepingScenarioTest extends AccountingTestCase
{
    /** @var array<string, int> */
    private array $accounts = [];

    /** @var array<string, int> */
    private array $categories = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->user->forceFill(['timezone' => 'Africa/Nairobi'])->save();
        $this->actingAs($this->user);
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Africa/Nairobi'));

        foreach ([
            'Bank' => ['bank', '120000'],
            'M-Pesa' => ['mobile_money', '3500'],
            'Cash' => ['cash', '2000'],
            'SACCO' => ['sacco', '50000'],
            'Card' => ['credit', '-15000'],
        ] as $name => [$type, $opening]) {
            $this->accounts[$name] = $this->postJson('/api/v1/accounting/accounts', [
                'name' => $name, 'type' => $type, 'currency' => 'KES', 'opening_balance' => $opening,
            ])->assertCreated()->json('data.id');
        }
        $this->categories = collect($this->getJson('/api/v1/accounting/categories')->json('data'))->pluck('id', 'name')->all();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_month_of_books_reconciles_to_the_cent(): void
    {
        $salary = $this->book('2026-10-01', 'income', 'Bank', '185000', 'Salary', key: 'october-salary');
        $this->book('2026-10-01', 'income', 'Bank', '185000', 'Salary', key: 'october-salary')->assertOk();
        $this->book('2026-10-01', 'expense', 'Bank', '45000', 'Housing');
        $this->transfer('2026-10-02', 'Bank', 'M-Pesa', '10000');
        $this->book('2026-10-02', 'fee', 'M-Pesa', '33', 'Financial');
        $groceries = $this->book('2026-10-03', 'expense', 'M-Pesa', '4250.75', 'Food');
        $this->transfer('2026-10-05', 'Bank', 'SACCO', '20000');
        $this->book('2026-10-10', 'expense', 'Card', '8999.99', 'Entertainment');
        $this->book('2026-10-12', 'refund', 'Card', '1500', 'Entertainment');
        $this->transfer('2026-10-15', 'Bank', 'Card', '15000');
        $this->book('2026-10-20', 'interest', 'SACCO', '412.50', 'Interest');
        $this->record(['type' => 'adjustment', 'direction' => 'out', 'account_id' => $this->accounts['Cash'], 'amount' => '150', 'date' => '2026-10-25'])->assertCreated();

        $mistake = $this->book('2026-10-28', 'expense', 'Bank', '2000', 'Food');
        $this->postJson("/api/v1/accounting/transactions/{$mistake->json('data.id')}/void")->assertOk();

        $fare = $this->book('2026-10-29', 'expense', 'Bank', '1200', 'Transport');
        $this->patchJson("/api/v1/accounting/transactions/{$fare->json('data.id')}", ['amount' => '1250', 'date' => '2026-10-30'])->assertOk();

        $this->book('2026-10-30', 'expense', 'Bank', '3000', 'Communication', status: 'pending');

        Carbon::setTestNow(Carbon::parse('2026-10-31 23:30', 'Africa/Nairobi'));
        $this->record(['type' => 'expense', 'account_id' => $this->accounts['M-Pesa'], 'category_id' => $this->categories['Transport'], 'amount' => '300'])
            ->assertCreated()
            ->assertJsonPath('data.date', '2026-10-31');

        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Africa/Nairobi'));
        $this->patchJson("/api/v1/accounting/transactions/{$salary->json('data.id')}", ['status' => 'reconciled'])->assertOk();
        $this->postJson("/api/v1/accounting/transactions/{$groceries->json('data.id')}/reverse")->assertCreated()->assertJsonPath('data.date', '2026-11-02');

        $this->assertOctober();
        $this->assertBalances();
        $this->assertTrialBalance();

        $november = $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-11-01&to=2026-11-30')->assertOk();
        $november->assertJsonPath('data.expenses.total', -425075)->assertJsonPath('data.net', 425075);
    }

    public function test_the_books_refuse_entries_an_accountant_would_reject(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00', 'Africa/Nairobi'));
        $salary = $this->book('2026-11-01', 'income', 'Bank', '185000', 'Salary');
        $id = $salary->json('data.id');

        $this->book('2026-11-10', 'expense', 'Bank', '500', 'Housing')->assertJsonValidationErrors('date');
        $cheque = $this->book('2026-11-10', 'expense', 'Bank', '500', 'Housing', status: 'pending')->assertCreated();
        $this->patchJson("/api/v1/accounting/transactions/{$cheque->json('data.id')}", ['status' => 'posted'])->assertJsonValidationErrors('date');
        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse", ['date' => '2026-10-31'])->assertJsonValidationErrors('date');
        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse", ['date' => '2026-11-03'])->assertJsonValidationErrors('date');

        $this->patchJson("/api/v1/accounting/accounts/{$this->accounts['Bank']}", ['archived' => true])->assertJsonValidationErrors('archived');

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['status' => 'reconciled'])->assertOk();
        $this->patchJson("/api/v1/accounting/accounts/{$this->accounts['Bank']}", ['opening_balance' => '1'])->assertJsonValidationErrors('opening_balance');
        $this->patchJson("/api/v1/accounting/accounts/{$this->accounts['Bank']}", ['name' => 'Equity Bank'])->assertOk();
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '1'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertJsonValidationErrors('status');

        $this->assertSame(30500000, $this->balance($this->model('Bank')));
        $this->assertSame(30450000, $this->getJson("/api/v1/accounting/accounts/{$this->accounts['Bank']}")->json('data.available_balance'));
    }

    private function assertOctober(): void
    {
        $statement = $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-10-01&to=2026-10-31')->assertOk();
        $statement
            ->assertJsonPath('data.income.total', 18541250)
            ->assertJsonPath('data.expenses.total', 5833374)
            ->assertJsonPath('data.net', 12707876);
        $this->assertSame(
            ['Salary' => 18500000, 'Interest' => 41250],
            collect($statement->json('data.income.categories'))->pluck('amount', 'name')->all(),
        );
        $this->assertSame(
            ['Housing' => 4500000, 'Entertainment' => 749999, 'Food' => 425075, 'Transport' => 155000, 'Financial' => 3300],
            collect($statement->json('data.expenses.categories'))->pluck('amount', 'name')->all(),
        );

        $cash = $this->getJson('/api/v1/accounting/reports/cash-flow?from=2026-10-01&to=2026-10-31')->assertOk();
        $cash
            ->assertJsonPath('data.opening', 16050000)
            ->assertJsonPath('data.inflows', 18691250)
            ->assertJsonPath('data.outflows', 5983374)
            ->assertJsonPath('data.net', 12707876)
            ->assertJsonPath('data.adjustments', -15000)
            ->assertJsonPath('data.closing', 28742876);
        $this->assertSame($cash->json('data.net'), $statement->json('data.net'), 'With no credit sales, cash flow equals net income.');

        $rows = collect($cash->json('data.accounts'))->keyBy('name');
        $this->assertSame([4500000, 0], [$rows['Bank']['transfers_out'], $rows['Bank']['transfers_in']]);
        $this->assertSame(1000000, $rows['M-Pesa']['transfers_in']);
        $this->assertSame(2000000, $rows['SACCO']['transfers_in']);
        $this->assertSame(1500000, $rows['Card']['transfers_in']);
        $this->assertEquals(
            ['Bank' => 21375000, 'M-Pesa' => 891625, 'Cash' => 185000, 'SACCO' => 7041250, 'Card' => -749999],
            $rows->map(fn (array $row) => $row['closing'])->only(['Bank', 'M-Pesa', 'Cash', 'SACCO', 'Card'])->all(),
        );
        $this->assertSame(0, $rows->sum('transfers_in') - $rows->sum('transfers_out'), 'Transfers create and destroy nothing.');

        $this->getJson('/api/v1/accounting/dashboard?from=2026-10-01&to=2026-10-31')
            ->assertOk()
            ->assertJsonPath('data.income', 18541250)
            ->assertJsonPath('data.expenses', 5833374)
            ->assertJsonPath('data.net_cash_flow', 12707876)
            ->assertJsonPath('data.balance', 29167951)
            ->assertJsonPath('data.available_balance', 28867951)
            ->assertJsonPath('data.pending.total', 300000);
    }

    private function assertBalances(): void
    {
        $balances = collect($this->getJson('/api/v1/accounting/accounts')->json('data'))->keyBy('name');

        $this->assertEquals(
            ['Bank' => 21375000, 'M-Pesa' => 1316700, 'Cash' => 185000, 'SACCO' => 7041250, 'Card' => -749999],
            $balances->map(fn (array $a) => $a['balance'])->only(['Bank', 'M-Pesa', 'Cash', 'SACCO', 'Card'])->all(),
        );
        $this->assertSame(21075000, $balances['Bank']['available_balance'], 'The pending bill is held back from what can be spent.');
        $this->assertSame(1316700, $balances['M-Pesa']['available_balance']);
    }

    private function assertTrialBalance(): void
    {
        $totals = DB::table('accounting_journal_lines')->selectRaw('kind, SUM(debit) - SUM(credit) AS net')->groupBy('kind')->pluck('net', 'kind');
        $this->assertSame(0, (int) $totals->sum(), 'Total debits equal total credits.');

        $openings = (int) DB::table('accounting_accounts')->sum('opening_balance');
        $closing = collect($this->getJson('/api/v1/accounting/accounts')->json('data'))->sum('balance');
        $this->assertSame(
            $closing - $openings,
            -(int) ($totals['income'] ?? 0) - (int) ($totals['expense'] ?? 0) - (int) ($totals['equity'] ?? 0),
            'What the accounts gained is what was earned, less what was spent, plus adjustments.',
        );
        $this->assertLedgerBalances();

        $this->assertSame(
            ['transaction.created' => 16, 'transaction.reversed' => 1, 'transaction.updated' => 2, 'transaction.voided' => 1],
            DB::table('accounting_audit_logs')->where('action', 'like', 'transaction.%')->groupBy('action')->selectRaw('action, COUNT(*) AS total')->pluck('total', 'action')->map(fn ($n) => (int) $n)->all(),
        );
    }

    private function book(string $date, string $type, string $account, string $amount, string $category, string $status = 'posted', ?string $key = null)
    {
        return $this->record([
            'type' => $type,
            'account_id' => $this->accounts[$account],
            'category_id' => $this->categories[$category],
            'amount' => $amount,
            'date' => $date,
            'status' => $status,
        ], $key);
    }

    private function transfer(string $date, string $from, string $to, string $amount): void
    {
        $this->record([
            'type' => 'transfer',
            'account_id' => $this->accounts[$from],
            'to_account_id' => $this->accounts[$to],
            'amount' => $amount,
            'date' => $date,
        ])->assertCreated();
    }

    private function model(string $name): FinancialAccount
    {
        return FinancialAccount::query()->findOrFail($this->accounts[$name]);
    }
}
