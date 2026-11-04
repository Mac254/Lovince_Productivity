<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Actions\RecordTransaction;
use App\Modules\Accounting\Actions\ReverseTransaction;
use App\Modules\Accounting\Actions\UpdateTransaction;
use App\Modules\Accounting\Actions\VoidTransaction;
use App\Modules\Accounting\Http\Requests\IndexTransactionRequest;
use App\Modules\Accounting\Http\Requests\TransactionRequest;
use App\Modules\Accounting\Http\Resources\TransactionResource;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Support\Money;
use App\Shared\Http\Controllers\Controller;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public const EXPORT_COLUMNS = ['Date', 'Type', 'Status', 'Account', 'To account', 'Category', 'Description', 'Reference', 'Direction', 'Amount', 'Currency', 'Notes'];

    public function index(IndexTransactionRequest $request): AnonymousResourceCollection
    {
        return TransactionResource::collection(
            $request->transactions()->paginate($request->validated('per_page') ?? Contract::PER_PAGE_DEFAULT)->withQueryString()
        );
    }

    public function store(TransactionRequest $request, RecordTransaction $record): JsonResponse
    {
        [$transaction, $created] = $record->handle($request->user(), $request->fields(), $request->validated('idempotency_key'));

        return $this->respond($transaction, $created ? 201 : 200);
    }

    public function show(Transaction $transaction): TransactionResource
    {
        Gate::authorize('view', $transaction);

        return TransactionResource::make($transaction->load(Transaction::RELATIONS));
    }

    public function update(TransactionRequest $request, Transaction $transaction, UpdateTransaction $update): JsonResponse
    {
        return $this->respond($update->handle($request->user(), $transaction, $request->fields()));
    }

    public function void(Request $request, Transaction $transaction, VoidTransaction $void): JsonResponse
    {
        Gate::authorize('update', $transaction);

        return $this->respond($void->handle($request->user(), $transaction));
    }

    public function reverse(Request $request, Transaction $transaction, ReverseTransaction $reverse): JsonResponse
    {
        Gate::authorize('update', $transaction);
        $input = $request->validate(['date' => ['sometimes', 'date_format:Y-m-d']]);

        [$reversal, $created] = $reverse->handle(
            $request->user(),
            $transaction,
            isset($input['date']) ? Carbon::createFromFormat('Y-m-d', $input['date']) : null,
        );

        return $this->respond($reversal, $created ? 201 : 200);
    }

    public function export(IndexTransactionRequest $request): StreamedResponse
    {
        $query = $request->transactions();

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, self::EXPORT_COLUMNS, escape: '');
            $query->chunk(500, fn ($rows) => $rows->each(fn (Transaction $t) => fputcsv($out, array_map($this->cell(...), [
                $t->date->toDateString(),
                $t->type->value,
                $t->status->value,
                $t->account?->name,
                $t->toAccount?->name,
                $t->category?->name,
                $t->description,
                $t->reference,
                $t->direction->value,
                Money::format($t->amount, $t->currency),
                $t->currency,
                $t->notes,
            ]), escape: '')));
            fclose($out);
        }, 'transactions-'.today()->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function respond(Transaction $transaction, int $status = 200): JsonResponse
    {
        return TransactionResource::make($transaction->load(Transaction::RELATIONS))->response()->setStatusCode($status);
    }

    /**
     * Keeps spreadsheet apps from running a cell as a formula.
     */
    private function cell(?string $value): string
    {
        $value ??= '';

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) && ! preg_match('/^-\d/', $value) ? "'".$value : $value;
    }
}
