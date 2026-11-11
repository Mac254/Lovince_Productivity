<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\RuleRequest;
use App\Modules\Accounting\Http\Resources\RuleResource;
use App\Modules\Accounting\Models\MoneyRule;
use App\Modules\Accounting\Services\RuleSuggestions;
use App\Shared\Http\Controllers\Controller;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RuleController extends Controller
{
    /**
     * Pinned rules first, then the user's own order; archived ones too with `?archived=1`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['archived' => ['sometimes', 'boolean']]);

        return RuleResource::collection(
            MoneyRule::query()
                ->whereBelongsTo($request->user())
                ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
                ->orderByRaw('CASE WHEN archived_at IS NULL THEN 0 ELSE 1 END')
                ->orderByDesc('pinned')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
        );
    }

    /**
     * Rules worth keeping, worked out from the user's goals and the last three months of spending.
     */
    public function suggestions(Request $request, RuleSuggestions $suggestions): JsonResponse
    {
        return response()->json(['data' => $suggestions->for($request->user())]);
    }

    public function store(RuleRequest $request): JsonResponse
    {
        $user = $request->user();
        $position = (int) MoneyRule::query()->whereBelongsTo($user)->max('position') + 1;
        $rule = $user->accountingRules()->create($request->fields() + ['position' => $position]);

        return RuleResource::make($rule->refresh())->response()->setStatusCode(201);
    }

    public function update(RuleRequest $request, MoneyRule $rule): RuleResource
    {
        $rule->update($request->fields());

        return RuleResource::make($rule);
    }

    /**
     * Puts the given rules in that order. Rules left out keep their place after them.
     */
    public function order(Request $request): Response
    {
        $user = $request->user();
        $ids = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.Contract::REORDER_MAX],
            'ids.*' => ['integer', 'distinct', Rule::exists('accounting_rules', 'id')->where('user_id', $user->id)],
        ])['ids'];

        DB::transaction(function () use ($user, $ids) {
            $rest = MoneyRule::query()->whereBelongsTo($user)->whereNotIn('id', $ids)->orderBy('position')->orderBy('id')->pluck('id');
            foreach ([...$ids, ...$rest] as $position => $id) {
                MoneyRule::query()->whereKey($id)->update(['position' => $position + 1]);
            }
        });

        return response()->noContent();
    }

    public function destroy(MoneyRule $rule): Response
    {
        Gate::authorize('delete', $rule);
        $rule->delete();

        return response()->noContent();
    }
}
