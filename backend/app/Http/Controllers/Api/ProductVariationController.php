<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\CreateProductVariationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariation\StoreProductVariationRequest;
use App\Http\Requests\ProductVariation\UpdateProductVariationRequest;
use App\Http\Resources\ProductVariationResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductVariationController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return ProductVariationResource::collection($product->variations()->get());
    }

    public function store(StoreProductVariationRequest $request, Product $product, CreateProductVariationAction $action): ProductVariationResource
    {
        $this->authorize('create', ProductVariation::class);

        $variation = $action->execute($product, $request->validated(), $request->user());

        return ProductVariationResource::make($variation);
    }

    public function update(UpdateProductVariationRequest $request, Product $product, ProductVariation $productVariation): ProductVariationResource
    {
        $this->authorize('update', $productVariation);

        $productVariation->update($request->validated());

        return ProductVariationResource::make($productVariation);
    }

    public function destroy(Product $product, ProductVariation $productVariation): \Illuminate\Http\Response
    {
        $this->authorize('delete', $productVariation);

        $productVariation->delete();

        return response()->noContent();
    }
}
