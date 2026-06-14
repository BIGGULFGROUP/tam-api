<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Category::orderBy('sort_order')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data     = $request->validate(['slug' => ['required', 'string', 'unique:categories,slug'], 'label' => ['required', 'string']]);
        $category = Category::create($data);
        return response()->json($category, 201);
    }

    public function show(string $slug): JsonResponse
    {
        return response()->json(Category::where('slug', $slug)->firstOrFail());
    }

    public function update(Request $request, string $slug): JsonResponse
    {
        $category = Category::where('slug', $slug)->firstOrFail();
        $category->update($request->except(['slug', 'id']));
        return response()->json($category->fresh());
    }
}
