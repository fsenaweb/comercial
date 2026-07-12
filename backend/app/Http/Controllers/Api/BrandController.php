<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Brand\StoreBrandRequest;
use App\Http\Requests\Brand\UpdateBrandRequest;
use App\Http\Resources\BrandResource;
use App\Models\Brand;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BrandController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return BrandResource::collection(Brand::orderBy('name')->get());
    }

    public function store(StoreBrandRequest $request): BrandResource
    {
        $this->authorize('create', Brand::class);

        return BrandResource::make(Brand::create($request->validated()));
    }

    public function show(Brand $brand): BrandResource
    {
        return BrandResource::make($brand);
    }

    public function update(UpdateBrandRequest $request, Brand $brand): BrandResource
    {
        $this->authorize('update', $brand);

        $brand->update($request->validated());

        return BrandResource::make($brand);
    }

    public function destroy(Brand $brand): \Illuminate\Http\Response
    {
        $this->authorize('delete', $brand);

        $brand->delete();

        return response()->noContent();
    }
}
