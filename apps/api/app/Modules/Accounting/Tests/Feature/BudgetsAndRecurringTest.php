<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

class BudgetsAndRecurringTest extends AccountingTestCase
{
    private FinancialAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user->forceFill(['timezone' => 'Africa/Nairobi'])->save();
        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00', 'Africa/Nairobi'));
        $this->bank = $this->account(['name' => 'Bank', 'currency' => 'KES', 'opening_balance' => 10_000_000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_budget_tracks_the_months_spending_net_of_refunds(): void
    {
        $food = $this->category('expense', 'Food');
        $budget = $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '15000'])
            ->assertCreated()
            ->assertJsonPath('data.amount', 1_500_000)
            ->assertJsonPath('data.spent', 0);

        $this->spend($food->id, '4000', '2026-11-02');
        $this->spend($food->id, '2500.50', '2026-11-10');
        $this->spend($food->id, '9000', '2026-10-30');
        $this->spend($food->id, '700', '2026-11-12', 'pending');
        $this->record(['type' => 'refund', 'account_id' => $this->bank->id, 'category_id' => $food->id, 'amount' => '500', 'date' => '2026-11-11'])->assertCreated();

        $this->getJson('/api/v1/accounting/budgets')
            ->assertOk()
            ->assertJsonPath('meta.month', '2026-11')
            ->assertJsonPath('data.0.spent', 600_050)
            ->assertJsonPath('data.0.remaining', 899_950);
        $this->getJson('/api/v1/accounting/budgets?month=2026-10')->assertJsonPath('data.0.spent', 900_000);

        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '1'])->assertJsonValidationErrors('category_id');
        $this->patchJson("/api/v1/accounting/budgets/{$budget->json('data.id')}", ['amount' => '5000'])->assertOk()->assertJsonPath('data.remaining', -100_050);
        $this->patchJson("/api/v1/accounting/budgets/{$budget->json('data.id')}", ['amount' => '0'])->assertJsonValidationErrors('amount');
        $this->deleteJson("/api/v1/accounting/budgets/{$budget->json('data.id')}")->assertNoContent();
    }

    public function test_a_refund_bigger_than_the_months_spending_leaves_the_budget_untouched(): void
    {
        $food = $this->category('expense', 'Food');
        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '100'])->assertCreated();
        $this->spend($food->id, '50', '2026-10-20');
        $this->record(['type' => 'refund', 'account_id' => $this->bank->id, 'category_id' => $food->id, 'amount' => '50', 'date' => '2026-11-02'])->assertCreated();

        $this->getJson('/api/v1/accounting/budgets?month=2026-11')
            ->assertJsonPath('data.0.spent', 0)
            ->assertJsonPath('data.0.remaining', 10000);
        $this->getJson('/api/v1/accounting/dashboard?currency=KES')->assertJsonPath('data.budgets.spent', 0);
    }

    public function test_the_dashboard_counts_one_budget_per_month_of_a_longer_period(): void
    {
        $food = $this->category('expense', 'Food');
        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '100'])->assertCreated();
        foreach (['2026-09-05', '2026-10-05', '2026-11-05'] as $date) {
            $this->spend($food->id, '80', $date);
        }

        $this->getJson('/api/v1/accounting/dashboard?from=2026-09-01&to=2026-11-30&currency=KES')
            ->assertJsonPath('data.budgets.amount', 30000)
            ->assertJsonPath('data.budgets.spent', 24000)
            ->assertJsonPath('data.budgets.over', 0);
    }

    public function test_budgets_take_only_your_own_spending_categories(): void
    {
        $salary = $this->category('income', 'Salary');
        $theirs = $this->category('expense', 'Food', User::factory()->create());

        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $salary->id, 'currency' => 'KES', 'amount' => '10'])->assertJsonValidationErrors('category_id');
        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $theirs->id, 'currency' => 'KES', 'amount' => '10'])->assertJsonValidationErrors('category_id');
    }

    public function test_a_recurring_payment_records_each_due_date_once_and_catches_up(): void
    {
        $rent = $this->category('expense', 'Housing');
        $rule = $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $this->bank->id, 'category_id' => $rent->id, 'amount' => '25000',
            'description' => 'Rent', 'frequency' => 'monthly', 'start_date' => '2026-10-31',
        ])->assertCreated()
            ->assertJsonPath('data.occurrences', 1)
            ->assertJsonPath('data.next_date', '2026-11-30');

        $this->assertSame(['2026-10-31'], Transaction::query()->pluck('date')->map->toDateString()->all());
        $this->assertSame(10_000_000 - 2_500_000, $this->balance($this->bank));

        Carbon::setTestNow(Carbon::parse('2027-03-01 00:20', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring')->assertSuccessful();
        $this->artisan('accounting:post-recurring')->assertSuccessful();

        $this->assertSame(
            ['2026-10-31', '2026-11-30', '2026-12-31', '2027-01-31', '2027-02-28'],
            Transaction::query()->orderBy('date')->pluck('date')->map->toDateString()->all(),
        );
        $this->assertSame(5, Transaction::query()->where('recurring_transaction_id', $rule->json('data.id'))->where('status', 'posted')->count());
        $this->getJson('/api/v1/accounting/recurring')->assertJsonPath('data.0.next_date', '2027-03-31');
        $this->assertLedgerBalances();
    }

    public function test_a_recurring_payment_waits_for_its_owners_midnight(): void
    {
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'income', 'account_id' => $this->bank->id, 'category_id' => $this->category('income', 'Salary')->id,
            'amount' => '185000', 'description' => 'Salary', 'frequency' => 'monthly', 'start_date' => '2026-12-01',
        ])->assertCreated()->assertJsonPath('data.occurrences', 0);

        Carbon::setTestNow(Carbon::parse('2026-11-30 23:50', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring');
        $this->assertSame(0, Transaction::count());

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:10', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring');
        $this->assertSame('2026-12-01', Transaction::sole()->date->toDateString());
    }

    public function test_pending_rules_leave_payments_to_confirm_and_end_dates_stop_them(): void
    {
        $bill = $this->category('expense', 'Communication');
        $rule = $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $this->bank->id, 'category_id' => $bill->id, 'amount' => '1500', 'status' => 'pending',
            'description' => 'Internet', 'frequency' => 'weekly', 'start_date' => '2026-11-01', 'end_date' => '2026-11-15',
        ])->assertCreated()->assertJsonPath('data.next_date', null);

        $this->assertSame(3, Transaction::query()->where('status', 'pending')->count());
        $this->assertSame(10_000_000, $this->balance($this->bank));

        $this->patchJson("/api/v1/accounting/recurring/{$rule->json('data.id')}", ['end_date' => '2026-10-01'])->assertJsonValidationErrors('end_date');
    }

    public function test_pausing_skips_the_paused_payments_and_rescheduling_never_repeats_one(): void
    {
        $rule = $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'transfer', 'account_id' => $this->bank->id, 'to_account_id' => $this->account(['name' => 'SACCO', 'currency' => 'KES'])->id,
            'amount' => '5000', 'description' => 'Savings', 'frequency' => 'weekly', 'start_date' => '2026-11-15',
        ])->assertCreated();
        $id = $rule->json('data.id');

        $this->patchJson("/api/v1/accounting/recurring/{$id}", ['paused' => true])->assertOk()->assertJsonPath('data.paused', true);
        Carbon::setTestNow(Carbon::parse('2026-12-10 09:00', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring');
        $this->assertSame(1, Transaction::count());

        $this->patchJson("/api/v1/accounting/recurring/{$id}", ['paused' => false])->assertOk()->assertJsonPath('data.next_date', '2026-12-13');
        $this->assertSame(1, Transaction::count());

        $this->patchJson("/api/v1/accounting/recurring/{$id}", ['start_date' => '2026-11-01'])->assertOk()->assertJsonPath('data.next_date', '2026-12-13');
        $this->assertSame(1, Transaction::count());
    }

    public function test_rules_check_the_money_like_a_transaction_and_belong_to_their_owner(): void
    {
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'refund', 'account_id' => $this->bank->id, 'amount' => '10', 'description' => 'x', 'frequency' => 'monthly', 'start_date' => '2026-11-15',
        ])->assertJsonValidationErrors('type');
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $this->bank->id, 'category_id' => $this->category('income', 'Salary')->id,
            'amount' => '10.001', 'description' => 'x', 'frequency' => 'monthly', 'start_date' => '2026-11-15',
        ])->assertJsonValidationErrors(['category_id', 'amount']);

        $other = User::factory()->create();
        $theirs = RecurringTransaction::query()->forceCreate([
            'user_id' => $other->id, 'account_id' => $this->account(owner: $other)->id, 'type' => 'expense', 'direction' => 'out', 'status' => 'posted',
            'amount' => 100, 'currency' => 'KES', 'description' => 'Theirs', 'frequency' => 'monthly', 'start_date' => '2026-12-01', 'next_date' => '2026-12-01',
        ]);
        $this->patchJson("/api/v1/accounting/recurring/{$theirs->id}", ['amount' => '1'])->assertNotFound();
        $this->deleteJson("/api/v1/accounting/recurring/{$theirs->id}")->assertNotFound();
        $this->getJson('/api/v1/accounting/recurring')->assertJsonCount(0, 'data');
    }

    public function test_archiving_an_account_pauses_its_rules(): void
    {
        $cash = $this->account(['name' => 'Cash', 'currency' => 'KES']);
        $rule = $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $cash->id, 'amount' => '100', 'description' => 'Bus', 'frequency' => 'weekly', 'start_date' => '2026-11-20',
        ])->assertCreated();
        $cash->forceFill(['archived_at' => now()])->save();

        Carbon::setTestNow(Carbon::parse('2026-11-21 09:00', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring');

        $this->assertSame(0, Transaction::count());
        $this->getJson('/api/v1/accounting/recurring')->assertJsonPath('data.0.paused', true);
        $this->deleteJson("/api/v1/accounting/recurring/{$rule->json('data.id')}")->assertNoContent();
    }

    public function test_an_account_used_by_a_rule_or_goal_keeps_its_currency_and_cant_be_deleted(): void
    {
        $cash = $this->account(['name' => 'Cash', 'currency' => 'KES']);
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $cash->id, 'amount' => '100', 'description' => 'Rent', 'frequency' => 'monthly', 'start_date' => '2026-12-01',
        ])->assertCreated();
        $saving = $this->account(['name' => 'Saving', 'currency' => 'KES']);
        $this->postJson('/api/v1/accounting/goals', ['name' => 'Car', 'kind' => 'savings', 'target_amount' => '1000', 'currency' => 'KES', 'account_id' => $saving->id])->assertCreated();

        $this->patchJson("/api/v1/accounting/accounts/{$cash->id}", ['currency' => 'UGX'])->assertJsonValidationErrors('currency');
        $this->patchJson("/api/v1/accounting/accounts/{$saving->id}", ['currency' => 'UGX'])->assertJsonValidationErrors('currency');
        $this->deleteJson("/api/v1/accounting/accounts/{$cash->id}")->assertJsonValidationErrors('account');
        $this->deleteJson("/api/v1/accounting/accounts/{$saving->id}")->assertJsonValidationErrors('account');
        $this->assertSame(1, RecurringTransaction::count());
    }

    public function test_a_rule_whose_account_changed_currency_is_paused_not_posted(): void
    {
        $cash = $this->account(['name' => 'Cash', 'currency' => 'KES']);
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $cash->id, 'amount' => '100', 'description' => 'Rent', 'frequency' => 'monthly', 'start_date' => '2026-12-01',
        ])->assertCreated();
        $cash->forceFill(['currency' => 'UGX'])->save();

        Carbon::setTestNow(Carbon::parse('2026-12-02 09:00', 'Africa/Nairobi'));
        $this->artisan('accounting:post-recurring')->assertSuccessful();

        $this->assertSame(0, Transaction::count());
        $this->getJson('/api/v1/accounting/recurring')->assertJsonPath('data.0.paused', true);
    }

    public function test_the_dashboard_and_trend_show_budgets_upcoming_payments_and_months(): void
    {
        $food = $this->category('expense', 'Food');
        $salary = $this->category('income', 'Salary');
        $this->postJson('/api/v1/accounting/budgets', ['category_id' => $food->id, 'currency' => 'KES', 'amount' => '3000'])->assertCreated();
        $this->spend($food->id, '4000', '2026-11-02');
        $this->spend($food->id, '1000', '2026-09-20');
        $this->record(['type' => 'income', 'account_id' => $this->bank->id, 'category_id' => $salary->id, 'amount' => '50000', 'date' => '2026-10-01'])->assertCreated();
        $this->postJson('/api/v1/accounting/recurring', [
            'type' => 'expense', 'account_id' => $this->bank->id, 'amount' => '999', 'description' => 'Gym', 'frequency' => 'monthly', 'start_date' => '2026-11-20',
        ])->assertCreated();

        $dashboard = $this->getJson('/api/v1/accounting/dashboard')->assertOk();
        $dashboard
            ->assertJsonPath('data.budgets.amount', 300_000)
            ->assertJsonPath('data.budgets.spent', 400_000)
            ->assertJsonPath('data.budgets.over', 1)
            ->assertJsonPath('data.upcoming.0.description', 'Gym')
            ->assertJsonPath('data.upcoming.0.next_date', '2026-11-20')
            ->assertJsonCount(6, 'data.trend')
            ->assertJsonPath('data.trend.5.month', '2026-11')
            ->assertJsonPath('data.trend.4.net', 5_000_000)
            ->assertJsonPath('data.trend.3.expenses', 100_000);
        $this->assertContains('over_budget', collect($dashboard->json('data.insights'))->pluck('key')->all());

        $this->getJson('/api/v1/accounting/reports/trend?from=2024-01-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonCount(24, 'data.months')
            ->assertJsonPath('data.months.23.expenses', 400_000);
    }

    private function spend(int $category, string $amount, string $date, string $status = 'posted'): void
    {
        $this->record(['type' => 'expense', 'account_id' => $this->bank->id, 'category_id' => $category, 'amount' => $amount, 'date' => $date, 'status' => $status])->assertCreated();
    }
}
