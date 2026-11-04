<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Http\Requests\PeriodRequest;
use App\Modules\Accounting\Http\Resources\TransactionResource;
use App\Modules\Accounting\Services\DefaultCategories;
use App\Modules\Accounting\Services\Overview;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(PeriodRequest $request, Overview $overview): JsonResponse
    {
        $user = $request->user();
        DefaultCategories::ensure($user);

        $data = $overview->build($user, $request->from(), $request->to(), Overview::currencyFor($user, $request->input('currency')));
        $data['recent'] = TransactionResource::collection($data['recent'])->resolve($request);

        return response()->json(['data' => $data]);
    }
}
