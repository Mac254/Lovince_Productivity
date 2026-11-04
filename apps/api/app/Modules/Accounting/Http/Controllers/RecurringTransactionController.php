<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Actions\PostRecurringTransactions;
use App\Modules\Accounting\Http\Requests\RecurringTransactionRequest;
use App\Modules\Accounting\Http\Resources\RecurringTransactionResource;
use App\Modules\Accounting\Models\RecurringTransaction;
use App\Modules\Accounting\Services\AuditTrail;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecurringTransactionController extends Controller
{
    public function __construct(private PostRecurringTransactions $post) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $rules = RecurringTransaction::query()
            ->whereBelongsTo($request->user())
            ->with(RecurringTransaction::RELATIONS)
            ->orderByRaw('CASE WHEN next_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('next_date')
            ->orderBy('description')
            ->get();

        return RecurringTransactionResource::collection($rules);
    }

    /**
     * Saves the rule and records any payment already due, so one starting today shows up at once.
     */
    public function store(RecurringTransactionRequest $request): JsonResponse
    {
        $rule = DB::transaction(function () use ($request) {
            $rule = new RecurringTransaction($request->fields());
            $rule->user()->associate($request->user());
            $rule->plan();
            $rule->save();
            AuditTrail::record($request->user(), 'recurring.created', $rule, AuditTrail::diff($rule, ['created_at', 'updated_at', 'user_id']));

            return $rule;
        });
        $this->post->handle($rule);

        return RecurringTransactionResource::make($rule->refresh()->load(RecurringTransaction::RELATIONS))->response()->setStatusCode(201);
    }

    /**
     * Payments already recorded stay as they are. A changed schedule, or a rule resumed after a
     * pause, picks up from today: only a new rule catches up on dates already past.
     */
    public function update(RecurringTransactionRequest $request, RecurringTransaction $recurring): RecurringTransactionResource
    {
        DB::transaction(function () use ($request, $recurring) {
            $resuming = $recurring->paused_at !== null && $request->has('paused') && ! $request->boolean('paused');
            $recurring->fill($request->fields());
            if ($request->reschedules() || $resuming) {
                $last = $recurring->transactions()->max('date');
                $recurring->plan($last === null ? today()->subDay() : Carbon::parse($last)->max(today()->subDay()));
            }
            $changes = AuditTrail::diff($recurring);
            $recurring->save();
            if ($changes !== []) {
                AuditTrail::record($request->user(), 'recurring.updated', $recurring, $changes);
            }
        });
        $this->post->handle($recurring);

        return RecurringTransactionResource::make($recurring->refresh()->load(RecurringTransaction::RELATIONS));
    }

    /**
     * Stops the payments. Those already recorded stay in the books.
     */
    public function destroy(Request $request, RecurringTransaction $recurring): Response
    {
        Gate::authorize('delete', $recurring);

        DB::transaction(function () use ($request, $recurring) {
            AuditTrail::record($request->user(), 'recurring.deleted', $recurring, ['description' => $recurring->description]);
            $recurring->delete();
        });

        return response()->noContent();
    }
}
