<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ProductController extends Controller
{
    /**
     * Paginado (antes trazia o catálogo inteiro numa página só — achado real
     * ao importar o catálogo do sistema legado: 13 mil produtos estouravam
     * a memória do PHP e mandavam 13,8MB de JSON pro navegador a cada
     * carregamento. Ver docs/11-migracao-sistema-legado.md.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min((int) $request->query('per_page', 25), 100);
        $search = trim((string) $request->query('search', ''));

        $query = Product::with(['unit', 'category', 'subcategory', 'brand', 'supplier', 'variations'])
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                    ->orWhereHas('variations', fn ($v) => $v->where('product_code', 'ilike', "%{$search}%"));
            });
        }

        return ProductResource::collection($query->paginate($perPage));
    }

    /**
     * Estatísticas do catálogo inteiro (cards da tela de Produtos) via
     * agregação no banco — antes calculadas no navegador iterando sobre
     * todos os produtos já carregados, inviável com o catálogo real.
     */
    public function summary(): JsonResponse
    {
        $totals = ProductVariation::query()->selectRaw('
                COUNT(*) as total_variations,
                COALESCE(SUM(current_quantity), 0) as total_stock_qty,
                COALESCE(SUM(current_quantity * sale_price), 0) as total_stock_value,
                COUNT(*) FILTER (WHERE min_quantity IS NOT NULL AND current_quantity <= min_quantity) as low_stock_count,
                COUNT(*) FILTER (WHERE current_quantity <= 0) as no_stock_count,
                COUNT(*) FILTER (WHERE max_quantity IS NOT NULL AND current_quantity > max_quantity) as excess_stock_count
            ')->first();

        return response()->json(['data' => [
            'total_products' => Product::count(),
            'total_stock_qty' => (int) $totals->total_stock_qty,
            'total_stock_value' => (string) $totals->total_stock_value,
            'low_stock_count' => (int) $totals->low_stock_count,
            'no_stock_count' => (int) $totals->no_stock_count,
            'excess_stock_count' => (int) $totals->excess_stock_count,
        ]]);
    }

    public function store(StoreProductRequest $request): ProductResource
    {
        $this->authorize('create', Product::class);

        $product = Product::create($request->validated());

        // Recarrega do banco: 'active' não é enviado no payload na maioria dos
        // cadastros (fica por conta do default da coluna), e save()/create()
        // não recarregam defaults de coluna no objeto em memória — sem isso a
        // resposta traria 'active' null em vez de true logo após a criação.
        // fresh() devolve uma instância nova, então precisa marcar
        // wasRecentlyCreated manualmente pro Resource continuar respondendo 201.
        $fresh = $product->fresh(['unit', 'category', 'subcategory', 'brand', 'supplier']);
        $fresh->wasRecentlyCreated = true;

        return ProductResource::make($fresh);
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

    public function destroy(Product $product): Response
    {
        $this->authorize('delete', $product);

        $product->delete();

        return response()->noContent();
    }
}
