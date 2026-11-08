<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

class GoalsTest extends AccountingTestCase
{
    private FinancialAccount $wallet;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00'));
        $this->wallet = $this->account(['name' => 'Income', 'type' => 'mobile_money', 'currency' => 'KES', 'opening_balance' => 5_000_000]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_a_goal_without_an_account_tracks_the_money_recorded_against_it(): void
    {
        $id = $this->postJson('/api/v1/accounting/goals', [
            'name' => ' Emergency fund ', 'kind' => 'emergency_fund', 'currency' => 'KES',
            'target_amount' => '60000', 'target_date' => '2027-04-30',
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Emergency fund')
            ->assertJsonPath('data.target_amount', 6_000_000)
            ->assertJsonPath('data.saved', 0)
            ->assertJsonPath('data.account', null)
            ->assertJsonPath('data.monthly_needed', 1_000_000)
            ->json('data.id');

        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '15000', 'date' => '2026-11-01', 'note' => 'First'])
            ->assertCreated()
            ->assertJsonPath('data.amount', 1_500_000);
        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '5000'])->assertCreated()->assertJsonPath('data.date', '2026-11-15');
        $out = $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '2000', 'withdraw' => true])
            ->assertCreated()
            ->assertJsonPath('data.amount', -200_000)
            ->json('data.id');

        $this->getJson("/api/v1/accounting/goals/{$id}")
            ->assertJsonPath('data.saved', 1_800_000)
            ->assertJsonPath('data.remaining', 4_200_000)
            ->assertJsonPath('data.monthly_needed', 700_000)
            ->assertJsonPath('data.achieved', false);
        $this->getJson("/api/v1/accounting/goals/{$id}/contributions")->assertJsonCount(3, 'data')->assertJsonPath('data.0.id', $out);

        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '18000.01', 'withdraw' => true])->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '0'])->assertJsonValidationErrors('amount');
        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '10', 'date' => '2026-11-16'])->assertJsonValidationErrors('date');

        $this->deleteJson("/api/v1/accounting/goals/{$id}/contributions/{$out}")->assertNoContent();
        $this->getJson("/api/v1/accounting/goals/{$id}")->assertJsonPath('data.saved', 2_000_000);
    }

    public function test_a_goal_linked_to_an_account_follows_its_ledger_balance(): void
    {
        $savings = $this->account(['name' => 'Savings', 'type' => 'savings', 'currency' => 'KES']);
        $id = $this->postJson('/api/v1/accounting/goals', [
            'name' => 'Land', 'kind' => 'investment', 'currency' => 'KES', 'target_amount' => '20000', 'account_id' => $savings->id,
        ])->assertCreated()
            ->assertJsonPath('data.account.name', 'Savings')
            ->assertJsonPath('data.saved', 0)
            ->assertJsonPath('data.monthly_needed', null)
            ->json('data.id');

        $this->record(['type' => 'transfer', 'account_id' => $this->wallet->id, 'to_account_id' => $savings->id, 'amount' => '25000', 'date' => '2026-11-10'])->assertCreated();

        $this->getJson('/api/v1/accounting/goals')
            ->assertJsonPath('data.0.saved', 2_500_000)
            ->assertJsonPath('data.0.remaining', 0)
            ->assertJsonPath('data.0.achieved', true);
        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '10'])->assertJsonValidationErrors('amount');

        $this->getJson('/api/v1/accounting/dashboard')
            ->assertJsonPath('data.goals.count', 1)
            ->assertJsonPath('data.goals.saved', 2_000_000)
            ->assertJsonPath('data.goals.items.0.name', 'Land');
    }

    public function test_a_linked_account_must_be_yours_open_and_in_the_goals_currency(): void
    {
        $dollars = $this->account(['currency' => 'USD']);
        $closed = $this->account(['currency' => 'KES', 'archived_at' => now()]);
        $theirs = $this->account(['currency' => 'KES'], User::factory()->create());
        $goal = ['name' => 'Car', 'kind' => 'savings', 'currency' => 'KES', 'target_amount' => '100'];

        $this->postJson('/api/v1/accounting/goals', $goal + ['account_id' => $dollars->id])->assertJsonValidationErrors('account_id');
        $this->postJson('/api/v1/accounting/goals', $goal + ['account_id' => $closed->id])->assertJsonValidationErrors('account_id');
        $this->postJson('/api/v1/accounting/goals', $goal + ['account_id' => $theirs->id])->assertJsonValidationErrors('account_id');
        $this->postJson('/api/v1/accounting/goals', $goal + ['target_date' => '2026-11-14'])->assertJsonValidationErrors('target_date');
        $this->postJson('/api/v1/accounting/goals', ['kind' => 'holiday'] + $goal)->assertJsonValidationErrors('kind');

        $id = $this->postJson('/api/v1/accounting/goals', $goal)->assertCreated()->json('data.id');
        $this->postJson("/api/v1/accounting/goals/{$id}/contributions", ['amount' => '10'])->assertCreated();
        $this->patchJson("/api/v1/accounting/goals/{$id}", ['account_id' => $this->wallet->id])->assertJsonValidationErrors('account_id');
        $this->patchJson("/api/v1/accounting/goals/{$id}", ['currency' => 'USD', 'target_amount' => '5'])->assertJsonValidationErrors('currency');
    }

    public function test_goals_can_be_edited_archived_and_deleted_only_by_their_owner(): void
    {
        $id = $this->postJson('/api/v1/accounting/goals', ['name' => 'Laptop', 'kind' => 'savings', 'currency' => 'KES', 'target_amount' => '80000'])->json('data.id');

        $this->patchJson("/api/v1/accounting/goals/{$id}", ['target_amount' => '90000', 'target_date' => '2026-12-31'])
            ->assertOk()
            ->assertJsonPath('data.target_amount', 9_000_000)
            ->assertJsonPath('data.monthly_needed', 4_500_000);
        $this->patchJson("/api/v1/accounting/goals/{$id}", ['archived' => true])->assertOk()->assertJsonPath('data.archived', true);
        $this->getJson('/api/v1/accounting/goals')->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/accounting/goals?archived=1')->assertJsonCount(1, 'data');

        Carbon::setTestNow(Carbon::parse('2027-02-01'));
        $this->patchJson("/api/v1/accounting/goals/{$id}", ['name' => 'New laptop', 'target_date' => '2026-12-31', 'archived' => false])->assertOk();

        $theirs = User::factory()->create()->accountingGoals()->create(['name' => 'Theirs', 'kind' => 'savings', 'currency' => 'KES', 'target_amount' => 100]);
        $this->getJson("/api/v1/accounting/goals/{$theirs->id}")->assertNotFound();
        $this->patchJson("/api/v1/accounting/goals/{$theirs->id}", ['name' => 'Mine'])->assertNotFound();
        $this->postJson("/api/v1/accounting/goals/{$theirs->id}/contributions", ['amount' => '10'])->assertNotFound();
        $this->deleteJson("/api/v1/accounting/goals/{$theirs->id}")->assertNotFound();

        $this->deleteJson("/api/v1/accounting/goals/{$id}")->assertNoContent();
        $this->assertDatabaseCount('accounting_goals', 1);
    }
}
