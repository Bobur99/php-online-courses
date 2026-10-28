<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private CategoryService $categoryService) {}

    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->categoryService->all(),
        ]);
    }

    public function store(CategoryRequest $request): JsonResponse
    {
        $this->authorize('create', Category::class);

        return response()->json($this->categoryService->create($request->validated()), 201);
    }

    public function update(
        CategoryRequest $request,
        Category $category
    ): JsonResponse {
        $this->authorize('update', $category);

        return response()->json($this->categoryService->update($category, $request->validated()));
    }

    public function destroy(Category $category): JsonResponse
    {
        $this->authorize('delete', $category);

        $this->categoryService->delete($category);

        return response()->json(status: 204);
    }
}
