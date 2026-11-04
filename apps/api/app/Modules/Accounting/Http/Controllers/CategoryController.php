<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Modules\Accounting\Enums\CategoryKind;
use App\Modules\Accounting\Http\Requests\CategoryRequest;
use App\Modules\Accounting\Http\Resources\CategoryResource;
use App\Modules\Accounting\Models\Category;
use App\Modules\Accounting\Services\AuditTrail;
use App\Modules\Accounting\Services\DefaultCategories;
use App\Shared\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'kind' => ['sometimes', 'nullable', Rule::enum(CategoryKind::class)],
            'archived' => ['sometimes', 'boolean'],
        ]);
        DefaultCategories::ensure($request->user());

        $categories = Category::query()
            ->whereBelongsTo($request->user())
            ->when($filters['kind'] ?? null, fn ($q, string $kind) => $q->where('kind', $kind))
            ->when(! $request->boolean('archived'), fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('kind')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $category = DB::transaction(function () use ($request) {
            $category = $request->user()->accountingCategories()->create($request->fields());
            AuditTrail::record($request->user(), 'category.created', $category);

            return $category;
        });

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(CategoryRequest $request, Category $category): CategoryResource
    {
        DB::transaction(function () use ($request, $category) {
            $category->fill($request->fields());
            $changes = AuditTrail::diff($category);
            $category->save();
            if ($changes !== []) {
                AuditTrail::record($request->user(), 'category.updated', $category, $changes);
            }
        });

        return CategoryResource::make($category);
    }

    public function destroy(Request $request, Category $category): Response
    {
        Gate::authorize('delete', $category);

        if ($category->is_default || $category->isUsed()) {
            throw ValidationException::withMessages(['category' => 'This category is a default or in use. Archive it instead.']);
        }

        DB::transaction(function () use ($request, $category) {
            AuditTrail::record($request->user(), 'category.deleted', $category, ['name' => $category->name]);
            $category->delete();
        });

        return response()->noContent();
    }
}
