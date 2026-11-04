<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\AccountRequest;
use App\Modules\Accounting\Http\Resources\AccountResource;
use App\Modules\Accounting\Models\FinancialAccount;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\Ledger;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    public function __construct(private Ledger $ledger) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['archived' => ['sometimes', 'boolean']]);

        $accounts = FinancialAccount::query()
            ->whereBelongsTo($request->user())
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
            ->orderBy('name')
            ->get();

        return AccountResource::collection($this->ledger->balances($accounts));
    }

    public function store(AccountRequest $request): JsonResponse
    {
        $account = DB::transaction(function () use ($request) {
            $account = $request->user()->financialAccounts()->create($request->fields());
            AuditTrail::record($request->user(), 'account.created', $account, [
                'currency' => $account->currency,
                'opening_balance' => $account->opening_balance,
            ]);

            return $account;
        });

        return AccountResource::make($this->withBalance($account))->response()->setStatusCode(201);
    }

    public function show(FinancialAccount $account): AccountResource
    {
        Gate::authorize('view', $account);

        return AccountResource::make($this->withBalance($account));
    }

    public function update(AccountRequest $request, FinancialAccount $account): AccountResource
    {
        DB::transaction(function () use ($request, $account) {
            $account->fill($request->fields());
            $changes = AuditTrail::diff($account);
            $account->save();
            if ($changes !== []) {
                AuditTrail::record($request->user(), 'account.updated', $account, $changes);
            }
        });

        return AccountResource::make($this->withBalance($account));
    }

    public function destroy(Request $request, FinancialAccount $account): Response
    {
        Gate::authorize('delete', $account);

        if ($account->hasActivity()) {
            throw ValidationException::withMessages(['account' => 'This account has transactions. Archive it instead.']);
        }

        DB::transaction(function () use ($request, $account) {
            AuditTrail::record($request->user(), 'account.deleted', $account, ['name' => $account->name]);
            $account->delete();
        });

        return response()->noContent();
    }

    private function withBalance(FinancialAccount $account): FinancialAccount
    {
        return $this->ledger->balances(collect([$account->refresh()]))->first();
    }
}
