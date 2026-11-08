<?php

namespace App\Modules\Books\Http\Controllers;

use App\Modules\Books\Enums\BookStatus;
use App\Modules\Books\Http\Requests\BookRequest;
use App\Modules\Books\Http\Resources\BookResource;
use App\Modules\Books\Models\Book;
use App\Modules\Tasks\Enums\TaskStatus;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class BookController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['sometimes', 'nullable', Rule::enum(BookStatus::class)],
            'goal_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $books = $this->withSummary(Book::query()->whereBelongsTo($request->user()))
            ->when($filters['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($filters['goal_id'] ?? null, fn ($q, int|string $goalId) => $q->where('goal_id', $goalId))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(500)
            ->get();

        return BookResource::collection($books);
    }

    public function store(BookRequest $request): JsonResponse
    {
        $book = $request->user()->books()->create($request->validated());

        return BookResource::make($this->detailed($book))->response()->setStatusCode(201);
    }

    public function show(Book $book): BookResource
    {
        Gate::authorize('view', $book);

        return BookResource::make($this->detailed($book));
    }

    public function update(BookRequest $request, Book $book): BookResource
    {
        $book->update($request->validated());

        return BookResource::make($this->detailed($book));
    }

    /**
     * Its reading log goes with it; its tasks stay and are unlinked.
     */
    public function destroy(Book $book): Response
    {
        Gate::authorize('delete', $book);

        $book->delete();

        return response()->noContent();
    }

    private function detailed(Book $book): Book
    {
        $book->load([
            ...Book::RELATIONS,
            'readings' => fn ($q) => $q->orderByDesc('read_on')->orderByDesc('id')->limit(100),
            'tasks' => fn ($q) => $q->orderByRaw('CASE WHEN completed_at IS NULL THEN 0 ELSE 1 END')->orderBy('due_date')->orderByDesc('id'),
        ]);

        return $book->loadSum('readings', 'minutes')
            ->loadMax('readings', 'read_on')
            ->loadCount(['tasks as open_tasks_count' => fn (Builder $q) => $q->whereIn('status', TaskStatus::open())]);
    }

    /**
     * @param  Builder<Book>  $query
     * @return Builder<Book>
     */
    private function withSummary(Builder $query): Builder
    {
        return $query->with(Book::RELATIONS)
            ->withSum('readings', 'minutes')
            ->withMax('readings', 'read_on')
            ->withCount(['tasks as open_tasks_count' => fn (Builder $q) => $q->whereIn('status', TaskStatus::open())]);
    }
}
