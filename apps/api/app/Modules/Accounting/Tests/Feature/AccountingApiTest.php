<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Services\DefaultCategories;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

class AccountingApiTest extends AccountingTestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_accounts_show_computed_balances(): void
    {
        $this->postJson('/api/v1/accounting/accounts', ['name' => 'M-Pesa', 'type' => 'mobile_money', 'currency' => 'kes', 'opening_balance' => '2,500.00'])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'KES')
            ->assertJsonPath('data.opening_balance', 250000)
            ->assertJsonPath('data.balance', 250000);

        $this->postJson('/api/v1/accounting/accounts', ['name' => 'Card', 'type' => 'credit', 'currency' => 'KES', 'opening_balance' => '-300'])
            ->assertJsonPath('data.balance', -30000);

        $this->postJson('/api/v1/accounting/accounts', ['name' => '', 'type' => 'crypto', 'currency' => 'XYZ'])
            ->assertJsonValidationErrors(['name', 'type', 'currency']);

        $this->postJson('/api/v1/accounting/accounts', ['name' => 'x', 'type' => 'bank', 'currency' => 'KES', 'balance' => 999999])
            ->assertJsonPath('data.balance', 0);
    }

    public function test_accounts_with_transactions_are_archived_not_deleted(): void
    {
        $used = $this->account();
        $empty = $this->account();
        $this->record(['type' => 'income', 'account_id' => $used->id, 'amount' => '10']);

        $this->deleteJson("/api/v1/accounting/accounts/{$used->id}")->assertJsonValidationErrors('account');
        $this->patchJson("/api/v1/accounting/accounts/{$used->id}", ['currency' => 'USD'])->assertJsonValidationErrors('currency');
        $this->patchJson("/api/v1/accounting/accounts/{$used->id}", ['archived' => true])->assertJsonValidationErrors('archived');
        $this->record(['type' => 'adjustment', 'direction' => 'out', 'account_id' => $used->id, 'amount' => '10']);
        $this->patchJson("/api/v1/accounting/accounts/{$used->id}", ['archived' => true])->assertJsonPath('data.archived', true);
        $this->deleteJson("/api/v1/accounting/accounts/{$empty->id}")->assertNoContent();

        $this->getJson('/api/v1/accounting/accounts')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/accounting/accounts?archived=1')->assertJsonCount(1, 'data')->assertJsonPath('data.0.balance', 0);
        $this->record(['type' => 'income', 'account_id' => $used->id, 'amount' => '10'])->assertJsonValidationErrors('account_id');
    }

    public function test_default_categories_are_created_once_and_custom_ones_can_be_added(): void
    {
        $this->getJson('/api/v1/accounting/categories')
            ->assertOk()
            ->assertJsonCount(count(DefaultCategories::INCOME) + count(DefaultCategories::EXPENSE), 'data');
        $this->getJson('/api/v1/accounting/categories?kind=expense')->assertJsonCount(count(DefaultCategories::EXPENSE), 'data');
        $this->assertNotContains('Savings', DefaultCategories::EXPENSE);

        $this->postJson('/api/v1/accounting/categories', ['kind' => 'expense', 'name' => 'Pets'])->assertCreated();
        $this->postJson('/api/v1/accounting/categories', ['kind' => 'expense', 'name' => 'Pets'])->assertJsonValidationErrors('name');
        $this->postJson('/api/v1/accounting/categories', ['kind' => 'income', 'name' => 'Pets'])->assertCreated();

        $food = Category::query()->whereBelongsTo($this->user)->where('name', 'Food')->first();
        $this->deleteJson("/api/v1/accounting/categories/{$food->id}")->assertJsonValidationErrors('category');
        $this->patchJson("/api/v1/accounting/categories/{$food->id}", ['archived' => true])->assertJsonPath('data.archived', true);

        $this->getJson('/api/v1/accounting/categories')->assertJsonCount(15, 'data');
    }

    public function test_a_category_must_match_the_transaction_type(): void
    {
        $bank = $this->account();
        $salary = $this->category('income', 'Salary');
        $food = $this->category();

        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => '1'])->assertJsonValidationErrors('category_id');
        $this->record(['type' => 'income', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '1'])->assertJsonValidationErrors('category_id');
        $this->record(['type' => 'adjustment', 'account_id' => $bank->id, 'amount' => '1'])->assertJsonValidationErrors('direction');
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $this->account()->id, 'category_id' => $food->id, 'amount' => '1'])
            ->assertCreated()
            ->assertJsonPath('data.category', null);
    }

    public function test_one_user_cannot_read_or_write_another_users_records(): void
    {
        $intruder = User::factory()->create();
        $account = $this->account();
        $category = $this->category();
        $id = $this->record(['type' => 'expense', 'account_id' => $account->id, 'category_id' => $category->id, 'amount' => '10'])->json('data.id');

        $this->actingAs($intruder);
        $theirs = $this->account([], $intruder);

        $this->getJson("/api/v1/accounting/accounts/{$account->id}")->assertNotFound();
        $this->patchJson("/api/v1/accounting/accounts/{$account->id}", ['name' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/accounting/accounts/{$account->id}")->assertNotFound();
        $this->patchJson("/api/v1/accounting/categories/{$category->id}", ['name' => 'Mine'])->assertNotFound();
        $this->getJson("/api/v1/accounting/transactions/{$id}")->assertNotFound();
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '1'])->assertNotFound();
        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertNotFound();
        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse")->assertNotFound();

        $this->record(['type' => 'expense', 'account_id' => $account->id, 'amount' => '1'])->assertJsonValidationErrors('account_id');
        $this->record(['type' => 'expense', 'account_id' => $theirs->id, 'category_id' => $category->id, 'amount' => '1'])->assertJsonValidationErrors('category_id');
        $this->record(['type' => 'transfer', 'account_id' => $theirs->id, 'to_account_id' => $account->id, 'amount' => '1'])->assertJsonValidationErrors('to_account_id');

        $this->getJson('/api/v1/accounting/transactions')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/accounting/accounts')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/accounting/dashboard')->assertJsonPath('data.balance', 0)->assertJsonCount(0, 'data.recent');
        $this->get('/api/v1/accounting/transactions/export')->assertOk()->assertDontSee('Test');

        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame('KES', $account->fresh()->currency);
    }

    public function test_guests_are_turned_away(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/v1/accounting/dashboard')->assertUnauthorized();
        $this->getJson('/api/v1/accounting/transactions')->assertUnauthorized();
    }

    public function test_transactions_can_be_filtered_and_searched(): void
    {
        $bank = $this->account();
        $cash = $this->account(['type' => 'cash']);
        $food = $this->category();
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '10', 'description' => 'Naivas groceries', 'date' => '2026-10-02']);
        $this->record(['type' => 'income', 'account_id' => $bank->id, 'amount' => '100', 'description' => 'Salary', 'reference' => 'QX12AB', 'date' => '2026-10-28']);
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $cash->id, 'amount' => '5', 'description' => 'ATM', 'date' => '2026-11-01']);

        $this->getJson('/api/v1/accounting/transactions')->assertJsonCount(3, 'data')->assertJsonPath('data.0.description', 'ATM');
        $this->getJson('/api/v1/accounting/transactions?search=naivas')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/accounting/transactions?search=QX12')->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/accounting/transactions?type=income')->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/accounting/transactions?account_id={$cash->id}")->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/accounting/transactions?category_id={$food->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/accounting/transactions?from=2026-10-01&to=2026-10-31')->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/accounting/transactions?from=2026-10-28&to=2026-10-28')->assertJsonCount(1, 'data');
    }

    public function test_transactions_export_to_csv(): void
    {
        $bank = $this->account(['name' => 'Equity']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '1500.5', 'description' => '=HYPERLINK("x")']);

        $csv = $this->get('/api/v1/accounting/transactions/export')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

        $this->assertStringContainsString('Date,Type,Status,Account', $csv);
        $this->assertStringContainsString('Equity', $csv);
        $this->assertStringContainsString('1500.50,KES', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }

    public function test_totals_are_correct_at_month_ends(): void
    {
        $bank = $this->account();
        $food = $this->category();
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '10', 'date' => '2026-01-31']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '20', 'date' => '2026-02-01']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '40', 'date' => '2026-02-28']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '80', 'date' => '2026-03-01']);

        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-01-01&to=2026-01-31')->assertJsonPath('data.expenses.total', 1000);
        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-02-01&to=2026-02-28')
            ->assertJsonPath('data.expenses.total', 6000)
            ->assertJsonPath('data.expenses.categories.0.name', 'Food');
        $this->getJson('/api/v1/accounting/reports/cash-flow?from=2026-02-01&to=2026-02-28')
            ->assertJsonPath('data.outflows', 6000)
            ->assertJsonPath('data.opening', -1000)
            ->assertJsonPath('data.closing', -7000)
            ->assertJsonPath('data.accounts.0.closing', -7000);
        $this->getJson('/api/v1/accounting/dashboard?from=2026-02-01&to=2026-02-28')
            ->assertJsonPath('data.expenses', 6000)
            ->assertJsonPath('data.previous.from', '2026-01-01')
            ->assertJsonPath('data.previous.to', '2026-01-31')
            ->assertJsonPath('data.previous_totals.expenses', 1000);
    }

    public function test_totals_follow_the_account_timezone(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-30 22:30:00', 'UTC'));
        $this->user->forceFill(['timezone' => 'Africa/Nairobi'])->save();
        $this->actingAs($this->user);
        $bank = $this->account();

        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '10'])->assertJsonPath('data.date', '2026-12-01');
        $this->getJson('/api/v1/accounting/dashboard')
            ->assertJsonPath('data.from', '2026-12-01')
            ->assertJsonPath('data.to', '2026-12-31')
            ->assertJsonPath('data.expenses', 1000);

        $this->app['auth']->forgetGuards();
        $other = User::factory()->create();
        $this->actingAs($other, 'web');
        $this->record(['type' => 'expense', 'account_id' => $this->account([], $other)->id, 'amount' => '10'])->assertJsonPath('data.date', '2026-11-30');
        $this->getJson('/api/v1/accounting/dashboard')->assertJsonPath('data.from', '2026-11-01')->assertJsonPath('data.expenses', 1000);
    }

    public function test_dashboard_summarises_the_period_without_transfers(): void
    {
        $bank = $this->account(['opening_balance' => 100000]);
        $savings = $this->account(['type' => 'savings']);
        $salary = $this->category('income', 'Salary');
        $food = $this->category();
        $transport = $this->category('expense', 'Transport');

        $this->record(['type' => 'income', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => '500', 'date' => '2026-11-01']);
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $savings->id, 'amount' => '200', 'date' => '2026-11-02']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '60', 'date' => '2026-11-02']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '30', 'date' => '2026-11-03']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $transport->id, 'amount' => '10', 'date' => '2026-11-03']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '25', 'status' => 'pending', 'date' => '2026-11-04']);
        foreach (['2026-10-03', '2026-10-10', '2026-10-20'] as $date) {
            $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '20', 'date' => $date]);
        }

        $response = $this->getJson('/api/v1/accounting/dashboard?from=2026-11-01&to=2026-11-30')
            ->assertOk()
            ->assertJsonPath('data.currency', 'KES')
            ->assertJsonPath('data.balance', 100000 + 50000 - 10000 - 6000)
            ->assertJsonPath('data.available_balance', 100000 + 50000 - 10000 - 6000 - 2500)
            ->assertJsonPath('data.income', 50000)
            ->assertJsonPath('data.expenses', 10000)
            ->assertJsonPath('data.net_cash_flow', 40000)
            ->assertJsonPath('data.pending.count', 1)
            ->assertJsonPath('data.top_expenses.0.name', 'Food');

        $insights = collect($response->json('data.insights'))->keyBy('key');
        $this->assertSame(67, $insights['spending_change']['percent']);
        $this->assertSame(90, $insights['top_category']['percent']);
        $this->assertSame(1, $insights['pending']['count']);
        $this->assertFalse($insights->has('negative_cash_flow'));
    }

    public function test_insights_stay_quiet_without_enough_data(): void
    {
        $bank = $this->account();
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '60']);

        $this->getJson('/api/v1/accounting/dashboard')->assertJsonPath('data.insights', []);
    }
}
