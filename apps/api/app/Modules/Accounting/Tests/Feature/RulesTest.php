<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\MoneyRule;
use App\Modules\Users\Models\User;
use Illuminate\Support\Carbon;

class RulesTest extends AccountingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-15 09:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rules_are_kept_pinned_first_in_the_users_order(): void
    {
        $first = $this->postJson('/api/v1/accounting/rules', ['title' => ' Pay myself first ', 'reason' => '  '])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Pay myself first')
            ->assertJsonPath('data.reason', null)
            ->assertJsonPath('data.pinned', false)
            ->json('data.id');
        $second = $this->postJson('/api/v1/accounting/rules', ['title' => 'Wait a day before buying anything over KES 5,000', 'reason' => 'Most urges pass.'])->json('data.id');
        $third = $this->postJson('/api/v1/accounting/rules', ['title' => 'No borrowing for wants', 'pinned' => true])->json('data.id');

        $this->getJson('/api/v1/accounting/rules')->assertJsonPath('data.*.id', [$third, $first, $second]);

        $this->putJson('/api/v1/accounting/rules/order', ['ids' => [$second, $first]])->assertNoContent();
        $this->getJson('/api/v1/accounting/rules')->assertJsonPath('data.*.id', [$third, $second, $first]);

        $this->patchJson("/api/v1/accounting/rules/{$second}", ['archived' => true, 'title' => 'Sleep on big buys'])
            ->assertOk()
            ->assertJsonPath('data.archived', true)
            ->assertJsonPath('data.title', 'Sleep on big buys');
        $this->getJson('/api/v1/accounting/rules')->assertJsonPath('data.*.id', [$third, $first]);
        $this->getJson('/api/v1/accounting/rules?archived=1')->assertJsonPath('data.*.id', [$third, $first, $second]);

        $this->postJson('/api/v1/accounting/rules', ['title' => ''])->assertJsonValidationErrors('title');
        $this->postJson('/api/v1/accounting/rules', ['title' => str_repeat('a', 121)])->assertJsonValidationErrors('title');

        $this->deleteJson("/api/v1/accounting/rules/{$first}")->assertNoContent();
        $this->assertDatabaseMissing('accounting_rules', ['id' => $first]);
    }

    public function test_rules_belong_to_their_owner(): void
    {
        $other = User::factory()->create();
        $theirs = $other->accountingRules()->create(['title' => 'Theirs']);

        $this->patchJson("/api/v1/accounting/rules/{$theirs->id}", ['title' => 'Mine'])->assertNotFound();
        $this->deleteJson("/api/v1/accounting/rules/{$theirs->id}")->assertNotFound();
        $this->putJson('/api/v1/accounting/rules/order', ['ids' => [$theirs->id]])->assertJsonValidationErrors('ids.0');
        $this->getJson('/api/v1/accounting/rules')->assertJsonCount(0, 'data');
        $this->assertSame('Theirs', MoneyRule::query()->find($theirs->id)->title);
    }

    public function test_suggestions_come_from_goals_and_spending_and_drop_once_added(): void
    {
        $wallet = $this->account(['name' => 'Income', 'type' => 'mobile_money', 'currency' => 'KES']);
        $rent = $this->category('expense', 'Rent');
        $food = $this->category('expense', 'Food');
        $this->record(['type' => 'income', 'account_id' => $wallet->id, 'category_id' => $this->category('income', 'Salary')->id, 'amount' => '35000', 'date' => '2026-11-01'])->assertCreated();
        $this->record(['type' => 'expense', 'account_id' => $wallet->id, 'category_id' => $rent->id, 'amount' => '12000', 'date' => '2026-11-02'])->assertCreated();
        $this->record(['type' => 'expense', 'account_id' => $wallet->id, 'category_id' => $food->id, 'amount' => '6000', 'date' => '2026-11-03'])->assertCreated();
        $this->postJson('/api/v1/accounting/goals', [
            'name' => 'Emergency fund', 'kind' => 'emergency_fund', 'currency' => 'KES', 'target_amount' => '60000', 'target_date' => '2027-04-30',
        ])->assertCreated();
        $pot = $this->postJson('/api/v1/accounting/goals', ['name' => 'Savings pot', 'kind' => 'savings', 'currency' => 'KES', 'target_amount' => '100000'])->json('data.id');

        $suggestions = collect($this->getJson('/api/v1/accounting/rules/suggestions')->assertOk()->json('data'))->keyBy('key');

        $this->assertSame([
            'pay-goals-first', 'emergency-fund-first', "goal-{$pot}", 'monthly-spending-cap', "category-{$rent->id}", "category-{$food->id}",
        ], $suggestions->keys()->all());
        $this->assertSame('On payday, move KES 10,000 to my goals before spending', $suggestions['pay-goals-first']['title']);
        $this->assertStringContainsString('29% of what comes in', $suggestions['pay-goals-first']['reason']);
        $this->assertSame('Keep monthly spending under KES 18,000', $suggestions['monthly-spending-cap']['title']);
        $this->assertSame('Keep Rent under KES 12,000 a month', $suggestions["category-{$rent->id}"]['title']);
        $this->assertSame('spending', $suggestions["category-{$rent->id}"]['source']);

        $this->postJson('/api/v1/accounting/rules', [
            'title' => $suggestions['pay-goals-first']['title'], 'reason' => $suggestions['pay-goals-first']['reason'], 'suggestion' => 'pay-goals-first',
        ])->assertCreated()->assertJsonPath('data.suggestion', 'pay-goals-first');

        $this->getJson('/api/v1/accounting/rules/suggestions')->assertJsonMissing(['key' => 'pay-goals-first']);
    }
}
