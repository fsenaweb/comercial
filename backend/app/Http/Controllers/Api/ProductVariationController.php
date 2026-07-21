<?php

namespace App\Http\Controllers\Api;

use App\Actions\Product\CreateProductVariationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductVariation\StoreProductVariationRequest;
use App\Http\Requests\ProductVariation\UpdateProductVariationRequest;
use App\Http\Resources\ProductVariationResource;
use App\Http\Resources\ProductVariationSearchResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductVariationController extends Controller
{
    public function index(Product $product): AnonymousResourceCollection
    {
        return ProductVariationResource::collection($product->variations()->get());
    }

    /**
     * Leitura de código de barras/código do produto no PDV e nas Etiquetas —
     * busca indexada e exata, sem carregar o catálogo inteiro no navegador
     * (ver docs/11-migracao-sistema-legado.md, achado com o catálogo real de
     * 13 mil produtos). Só produto ativo.
     */
    public function lookup(Request $request): JsonResponse
    {
        $code = trim((string) $request->query('code', ''));

        $variation = ProductVariation::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->where(fn ($q) => $q->where('product_code', $code)->orWhere('ean_gtin', $code))
            ->first();

        if (! $variation) {
            return response()->json(['data' => null], 404);
        }

        return response()->json(['data' => ProductVariationSearchResource::make($variation)]);
    }

    /**
     * Autocomplete por nome/código (PDV, seletor F2, Etiquetas) — busca no
     * banco, limitada, em vez do filtro client-side sobre o catálogo inteiro
     * que existia antes.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min((int) $request->query('limit', 20), 50);

        $variations = ProductVariation::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->when($term !== '', function ($query) use ($term) {
                $query->where(function ($q) use ($term) {
                    $q->where('product_code', 'ilike', "%{$term}%")
                        ->orWhere('ean_gtin', 'ilike', "%{$term}%")
                        ->orWhereHas('product', fn ($q2) => $q2->where('name', 'ilike', "%{$term}%"));
                });
            })
            ->orderBy(Product::select('name')->whereColumn('products.id', 'product_variations.product_id'))
            ->limit($limit)
            ->get();

        return ProductVariationSearchResource::collection($variations);
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

    public function destroy(Product $product, ProductVariation $productVariation): Response
    {
        $this->authorize('delete', $productVariation);

        $productVariation->delete();

        return response()->noContent();
    }
}
