<?php

namespace App\Modules\Search\Http\Controllers;

use App\Modules\Search\Services\SearchService;
use App\Shared\Http\Controllers\Controller;
use App\Support\Contract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request, SearchService $search): JsonResponse
    {
        $query = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:'.Contract::SEARCH_MAX],
        ])['q'];

        return response()->json(['data' => $search->for($request->user(), trim($query))]);
    }
}
