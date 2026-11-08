<?php

namespace App\Modules\Books\Http\Controllers;

use App\Modules\Books\Http\Requests\BookReadingRequest;
use App\Modules\Books\Http\Resources\BookReadingResource;
use App\Modules\Books\Models\Book;
use App\Modules\Books\Models\BookReading;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BookReadingController extends Controller
{
    public function store(BookReadingRequest $request, Book $book): JsonResponse
    {
        Gate::authorize('update', $book);

        $reading = DB::transaction(function () use ($request, $book) {
            $reading = new BookReading($request->validated());
            $reading->user()->associate($request->user());
            $reading->book()->associate($book);
            $reading->save();

            return $reading;
        });

        return BookReadingResource::make($reading)->response()->setStatusCode(201);
    }

    public function update(BookReadingRequest $request, BookReading $reading): BookReadingResource
    {
        DB::transaction(fn () => $reading->update($request->validated()));

        return BookReadingResource::make($reading);
    }

    public function destroy(BookReading $reading): Response
    {
        Gate::authorize('delete', $reading);

        DB::transaction(fn () => $reading->delete());

        return response()->noContent();
    }
}
