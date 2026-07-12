<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return ProductResource::collection(
            Product::with(['unit', 'category', 'subcategory', 'brand', 'supplier', 'variations'])
                ->orderBy('name')
                ->get()
        );
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->validated());

        return ProductResource::make($product->load(['unit', 'category', 'subcategory', 'brand', 'supplier']));
    }

    public function show(Product $product): ProductResource
    {
        return ProductResource::make(
            $product->load(['unit', 'category', 'subcategory', 'brand', 'supplier', 'variations'])
        );
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->authorize('update', $product);

        $product->update($request->validated());

        return ProductResource::make($product->load(['unit', 'category', 'subcategory', 'brand', 'supplier', 'variations']));
    }

    public function destroy(Product $product): \Illuminate\Http\Response
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
