<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\QuoteResource;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuoteController extends Controller
{
    public function random(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'exclude' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $quote = Quote::pickRandom($validated['exclude'] ?? null);

        return response()->json([
            'data' => $quote ? QuoteResource::make($quote)->resolve() : null,
        ]);
    }
}
