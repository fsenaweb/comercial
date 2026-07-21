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
     *
     * Termo tokenizado por espaço (pedido do cliente, 2026-07-21): cada
     * palavra precisa aparecer em algum lugar (nome, código ou EAN), em
     * qualquer ordem — "paraf x100" acha "PARAFUSO SEXTAVADO M8 X100" mesmo
     * sem "x100" vir logo depois de "paraf" no nome. Antes o termo inteiro
     * era um único `ILIKE '%...%'`, que exige a frase inteira contígua.
     * Um `%` literal digitado pelo usuário (ex.: "paraf%x100") continua
     * funcionando como coringa de qualquer jeito — não é escapado, o
     * Postgres já trata `%` dentro do padrão do ILIKE como curinga.
     */
    public function search(Request $request): AnonymousResourceCollection
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min((int) $request->query('limit', 20), 50);
        $words = array_values(array_filter(preg_split('/\s+/', $term)));

        $variations = ProductVariation::query()
            ->with('product')
            ->whereHas('product', fn ($q) => $q->where('active', true))
            ->when($words !== [], function ($query) use ($words) {
                $query->where(function ($q) use ($words) {
                    foreach ($words as $word) {
                        $q->where(function ($qWord) use ($word) {
                            $qWord->where('product_code', 'ilike', "%{$word}%")
                                ->orWhere('ean_gtin', 'ilike', "%{$word}%")
                                ->orWhereHas('product', fn ($q2) => $q2->where('name', 'ilike', "%{$word}%"));
                        });
                    }
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
