<?php

namespace App\Modules\Accounting\Tests\Feature;

use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class AccountingTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function account(array $attributes = [], ?User $owner = null): FinancialAccount
    {
        return FinancialAccount::factory()->for($owner ?? $this->user)->create($attributes);
    }

    protected function category(string $kind = 'expense', string $name = 'Food', ?User $owner = null): Category
    {
        return Category::factory()->for($owner ?? $this->user)->create(['kind' => $kind, 'name' => $name]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    protected function record(array $input, ?string $key = null): TestResponse
    {
        return $this->postJson('/api/v1/accounting/transactions', $input + ['description' => 'Test'], [
            'Idempotency-Key' => $key ?? (string) Str::uuid(),
        ]);
    }

    protected function balance(FinancialAccount $account): int
    {
        return $this->getJson("/api/v1/accounting/accounts/{$account->id}")->json('data.balance');
    }

    protected function assertLedgerBalances(): void
    {
        $unbalanced = DB::table('accounting_journal_lines')
            ->groupBy('journal_entry_id')
            ->havingRaw('SUM(debit) <> SUM(credit)')
            ->pluck('journal_entry_id');

        $this->assertCount(0, $unbalanced, 'Every journal entry debits what it credits.');
    }
}
