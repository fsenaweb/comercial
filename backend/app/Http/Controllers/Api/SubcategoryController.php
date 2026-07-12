<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subcategory\StoreSubcategoryRequest;
use App\Http\Requests\Subcategory\UpdateSubcategoryRequest;
use App\Http\Resources\SubcategoryResource;
use App\Models\Subcategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubcategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return SubcategoryResource::collection(
            Subcategory::with('category')->orderBy('name')->get()
        );
    }

    public function store(StoreSubcategoryRequest $request): SubcategoryResource
    {
        $this->authorize('create', Subcategory::class);

        return SubcategoryResource::make(Subcategory::create($request->validated()));
    }

    public function show(Subcategory $subcategory): SubcategoryResource
    {
        return SubcategoryResource::make($subcategory->load('category'));
    }

    public function update(UpdateSubcategoryRequest $request, Subcategory $subcategory): SubcategoryResource
    {
        $this->authorize('update', $subcategory);

        $subcategory->update($request->validated());

        return SubcategoryResource::make($subcategory);
    }

    public function destroy(Subcategory $subcategory): \Illuminate\Http\Response
    {
        $this->authorize('delete', $subcategory);

        $subcategory->delete();

        return response()->noContent();
    }
}
