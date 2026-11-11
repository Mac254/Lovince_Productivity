<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\AuditLog;
use App\Modules\Accounting\Models\JournalEntry;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Users\Models\User;
use Illuminate\Support\Facades\DB;

class LedgerInvariantsTest extends AccountingTestCase
{
    public function test_amounts_are_stored_as_integer_minor_units(): void
    {
        $account = $this->account();

        $this->record(['type' => 'income', 'account_id' => $account->id, 'amount' => '1,500.50'])
            ->assertCreated()
            ->assertJsonPath('data.amount', 150050)
            ->assertJsonPath('data.currency', 'KES')
            ->assertJsonPath('data.direction', 'in');

        $this->record(['type' => 'income', 'account_id' => $account->id, 'amount' => '1.505'])->assertJsonValidationErrors('amount');
        $this->record(['type' => 'income', 'account_id' => $account->id, 'amount' => 12.5])->assertJsonValidationErrors('amount');
        $this->record(['type' => 'income', 'account_id' => $account->id, 'amount' => '0'])->assertJsonValidationErrors('amount');

        $ugx = $this->account(['currency' => 'UGX']);
        $this->record(['type' => 'income', 'account_id' => $ugx->id, 'amount' => '5000'])->assertJsonPath('data.amount', 5000);
        $this->record(['type' => 'income', 'account_id' => $ugx->id, 'amount' => '5000.50'])->assertJsonValidationErrors('amount');
    }

    public function test_transfers_create_or_destroy_no_money(): void
    {
        $bank = $this->account(['opening_balance' => 100000]);
        $mpesa = $this->account(['type' => 'mobile_money', 'opening_balance' => 5000]);

        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $mpesa->id, 'amount' => '250.00'])->assertCreated();

        $this->assertSame(75000, $this->balance($bank));
        $this->assertSame(30000, $this->balance($mpesa));
        $this->assertSame(105000, $this->balance($bank) + $this->balance($mpesa));
        $this->assertLedgerBalances();

        $this->getJson('/api/v1/accounting/reports/income-statement')
            ->assertJsonPath('data.income.total', 0)
            ->assertJsonPath('data.expenses.total', 0);
        $this->getJson('/api/v1/accounting/reports/cash-flow')
            ->assertJsonPath('data.inflows', 0)
            ->assertJsonPath('data.outflows', 0)
            ->assertJsonPath('data.opening', 105000)
            ->assertJsonPath('data.closing', 105000);
    }

    public function test_transfers_need_two_different_accounts_in_one_currency(): void
    {
        $bank = $this->account();
        $usd = $this->account(['currency' => 'USD']);

        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'amount' => '10'])->assertJsonValidationErrors('to_account_id');
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $bank->id, 'amount' => '10'])->assertJsonValidationErrors('to_account_id');
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $usd->id, 'amount' => '10'])->assertJsonValidationErrors('to_account_id');
    }

    public function test_balances_reconcile_with_stored_transactions(): void
    {
        $bank = $this->account(['opening_balance' => 50000]);
        $cash = $this->account(['type' => 'cash']);
        $food = $this->category();
        $salary = $this->category('income', 'Salary');

        $this->record(['type' => 'income', 'account_id' => $bank->id, 'category_id' => $salary->id, 'amount' => '1000']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '120.25']);
        $this->record(['type' => 'fee', 'account_id' => $bank->id, 'amount' => '5']);
        $this->record(['type' => 'refund', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '20']);
        $this->record(['type' => 'interest', 'account_id' => $bank->id, 'amount' => '1.10']);
        $this->record(['type' => 'adjustment', 'direction' => 'out', 'account_id' => $bank->id, 'amount' => '0.85']);
        $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $cash->id, 'amount' => '300']);
        $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '999', 'status' => 'pending']);

        $posted = Transaction::query()->whereIn('status', ['posted', 'reconciled'])->get();
        $expected = $bank->opening_balance;
        foreach ($posted as $t) {
            $sign = $t->direction->value === 'in' ? 1 : -1;
            if ($t->account_id === $bank->id) {
                $expected += $sign * $t->amount;
            }
            if ($t->to_account_id === $bank->id) {
                $expected += $t->amount;
            }
        }

        $this->assertSame(50000 + 100000 - 12025 - 500 + 2000 + 110 - 85 - 30000, $expected);
        $this->getJson("/api/v1/accounting/accounts/{$bank->id}")
            ->assertJsonPath('data.balance', $expected)
            ->assertJsonPath('data.available_balance', $expected - 99900)
            ->assertJsonPath('data.total_inflows', 100000 + 2000 + 110)
            ->assertJsonPath('data.total_outflows', 12025 + 500 + 85 + 30000);
        $this->assertSame(30000, $this->balance($cash));
        $this->assertLedgerBalances();

        $this->getJson('/api/v1/accounting/reports/income-statement')
            ->assertJsonPath('data.income.total', 100110)
            ->assertJsonPath('data.expenses.total', 12025 + 500 - 2000)
            ->assertJsonPath('data.net', 100110 - 10525);
    }

    public function test_pending_amounts_never_change_the_posted_balance(): void
    {
        $bank = $this->account(['opening_balance' => 10000]);

        $id = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '40', 'status' => 'pending'])->json('data.id');
        $this->record(['type' => 'income', 'account_id' => $bank->id, 'amount' => '70', 'status' => 'pending']);

        $this->getJson("/api/v1/accounting/accounts/{$bank->id}")
            ->assertJsonPath('data.balance', 10000)
            ->assertJsonPath('data.available_balance', 6000);
        $this->assertSame(0, JournalEntry::query()->count());

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['status' => 'posted'])->assertOk();
        $this->getJson("/api/v1/accounting/accounts/{$bank->id}")
            ->assertJsonPath('data.balance', 6000)
            ->assertJsonPath('data.available_balance', 6000);
    }

    public function test_a_duplicate_request_posts_nothing(): void
    {
        $bank = $this->account();
        $input = ['type' => 'expense', 'account_id' => $bank->id, 'amount' => '75'];

        $first = $this->record($input, 'checkout-0001')->assertCreated();
        $this->record($input, 'checkout-0001')->assertOk()->assertJsonPath('data.id', $first->json('data.id'));

        $this->assertSame(1, Transaction::query()->count());
        $this->assertSame(1, JournalEntry::query()->count());
        $this->assertSame(-7500, $this->balance($bank));

        $this->postJson('/api/v1/accounting/transactions', $input + ['description' => 'No key'])->assertJsonValidationErrors('idempotency_key');
    }

    public function test_the_same_key_from_another_user_is_a_separate_request(): void
    {
        $this->record(['type' => 'income', 'account_id' => $this->account()->id, 'amount' => '1'], 'shared-key-1')->assertCreated();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->record(['type' => 'income', 'account_id' => $this->account([], $other)->id, 'amount' => '1'], 'shared-key-1')->assertCreated();

        $this->assertSame(2, Transaction::query()->count());
    }

    public function test_editing_a_posted_transaction_keeps_its_history(): void
    {
        $bank = $this->account();
        $id = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '100', 'date' => '2026-10-10'])->json('data.id');

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '80', 'date' => '2026-11-02'])
            ->assertOk()
            ->assertJsonPath('data.amount', 8000);

        $this->assertSame(-8000, $this->balance($bank));
        $this->assertSame(3, JournalEntry::query()->where('transaction_id', $id)->count());
        $this->assertSame(1, JournalEntry::query()->where('transaction_id', $id)->effective()->count());
        $this->assertLedgerBalances();

        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-10-01&to=2026-10-31')->assertJsonPath('data.expenses.total', 0);
        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-11-01&to=2026-11-30')->assertJsonPath('data.expenses.total', 8000);

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['description' => 'Groceries'])->assertOk();
        $this->assertSame(3, JournalEntry::query()->where('transaction_id', $id)->count());

        $log = AuditLog::query()->where('subject_id', $id)->where('action', 'transaction.updated')->first();
        $this->assertSame([10000, 8000], $log->changes['amount']);
    }

    public function test_reversals_keep_history(): void
    {
        $bank = $this->account(['opening_balance' => 20000]);
        $food = $this->category();
        $id = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'category_id' => $food->id, 'amount' => '50', 'date' => '2026-10-15'])->json('data.id');

        $reversal = $this->postJson("/api/v1/accounting/transactions/{$id}/reverse", ['date' => '2026-11-03'])
            ->assertCreated()
            ->assertJsonPath('data.reversal_of_id', $id)
            ->assertJsonPath('data.direction', 'in')
            ->assertJsonPath('data.amount', 5000);
        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse")->assertOk()->assertJsonPath('data.id', $reversal->json('data.id'));

        $this->assertSame(20000, $this->balance($bank));
        $this->assertSame(2, Transaction::query()->count());
        $this->getJson("/api/v1/accounting/transactions/{$id}")
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.reversed_by_id', $reversal->json('data.id'));

        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-10-01&to=2026-10-31')->assertJsonPath('data.expenses.total', 5000);
        $this->getJson('/api/v1/accounting/reports/income-statement?from=2026-11-01&to=2026-11-30')->assertJsonPath('data.expenses.total', -5000);

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '10'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertJsonValidationErrors('status');
        $this->postJson('/api/v1/accounting/transactions/'.$reversal->json('data.id').'/reverse')->assertJsonValidationErrors('transaction');
        $this->assertLedgerBalances();
    }

    public function test_reversing_a_transfer_moves_the_money_back(): void
    {
        $bank = $this->account(['opening_balance' => 10000]);
        $sacco = $this->account(['type' => 'sacco']);
        $id = $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $sacco->id, 'amount' => '40'])->json('data.id');

        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse")->assertCreated()
            ->assertJsonPath('data.account.id', $sacco->id)
            ->assertJsonPath('data.to_account.id', $bank->id);

        $this->assertSame(10000, $this->balance($bank));
        $this->assertSame(0, $this->balance($sacco));
    }

    public function test_a_reversal_never_touches_an_archived_account(): void
    {
        $bank = $this->account(['opening_balance' => 10000]);
        $sacco = $this->account(['name' => 'Sacco', 'type' => 'sacco']);
        $id = $this->record(['type' => 'transfer', 'account_id' => $bank->id, 'to_account_id' => $sacco->id, 'amount' => '40'])->json('data.id');
        $this->record(['type' => 'transfer', 'account_id' => $sacco->id, 'to_account_id' => $bank->id, 'amount' => '40'])->assertCreated();
        $this->patchJson("/api/v1/accounting/accounts/{$sacco->id}", ['archived' => true])->assertOk();

        $this->postJson("/api/v1/accounting/transactions/{$id}/reverse")
            ->assertJsonValidationErrors(['transaction' => 'Unarchive Sacco to reverse this.']);
        $this->assertSame(0, $this->balance($sacco));
        $this->assertSame(10000, $this->balance($bank));
    }

    public function test_an_edit_from_a_stale_form_is_a_conflict(): void
    {
        $bank = $this->account();
        $created = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '100'])->assertCreated();
        $id = $created->json('data.id');
        $stale = $created->json('data.updated_at');

        $this->travel(1)->minutes();
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '120', 'base_updated_at' => $stale])->assertOk();
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '90', 'base_updated_at' => $stale])->assertConflict();

        $this->assertSame(-12000, $this->balance($bank));
        $this->assertLedgerBalances();
    }

    public function test_voiding_keeps_the_record_and_cancels_its_entry(): void
    {
        $bank = $this->account();
        $id = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '30'])->json('data.id');

        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertOk()->assertJsonPath('data.status', 'voided');
        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertOk();

        $this->assertSame(0, $this->balance($bank));
        $this->assertSame(2, JournalEntry::query()->count());
        $this->assertSame(0, JournalEntry::query()->effective()->count());
        $this->assertNotNull(Transaction::query()->find($id));
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '1'])->assertJsonValidationErrors('status');
        $this->deleteJson("/api/v1/accounting/transactions/{$id}")->assertMethodNotAllowed();
        $this->getJson("/api/v1/accounting/accounts/{$bank->id}")
            ->assertJsonPath('data.total_inflows', 0)
            ->assertJsonPath('data.total_outflows', 0);
    }

    public function test_reconciled_transactions_are_locked(): void
    {
        $bank = $this->account();
        $id = $this->record(['type' => 'expense', 'account_id' => $bank->id, 'amount' => '30'])->json('data.id');

        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['status' => 'reconciled'])->assertOk();
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['amount' => '31'])->assertJsonValidationErrors('status');
        $this->postJson("/api/v1/accounting/transactions/{$id}/void")->assertJsonValidationErrors('status');
        $this->patchJson("/api/v1/accounting/transactions/{$id}", ['notes' => 'Checked against statement'])->assertOk();
        $this->assertSame(1, JournalEntry::query()->count());
    }

    public function test_every_write_leaves_an_audit_trail(): void
    {
        $this->postJson('/api/v1/accounting/accounts', ['name' => 'Equity Bank', 'type' => 'bank', 'currency' => 'KES', 'opening_balance' => '100'])->assertCreated();
        $account = $this->user->financialAccounts()->first();
        $id = $this->record(['type' => 'income', 'account_id' => $account->id, 'amount' => '5'])->json('data.id');
        $this->postJson("/api/v1/accounting/transactions/{$id}/void");

        $this->assertSame(
            ['account.created', 'transaction.created', 'transaction.voided'],
            AuditLog::query()->where('user_id', $this->user->id)->orderBy('id')->pluck('action')->all(),
        );
    }

    public function test_a_failed_write_leaves_nothing_behind(): void
    {
        $bank = $this->account();
        DB::listen(function ($query) {
            if (str_starts_with($query->sql, 'insert into "accounting_journal_lines"')) {
                throw new \RuntimeException('Disk full');
            }
        });

        try {
            $this->withoutExceptionHandling()->record(['type' => 'income', 'account_id' => $bank->id, 'amount' => '5']);
        } catch (\RuntimeException) {
        }

        $this->assertSame(0, Transaction::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }
}
