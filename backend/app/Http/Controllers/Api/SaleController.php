<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\CancelSaleAction;
use App\Actions\Sale\RegisterSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\CancelSaleRequest;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sale::query()->with(['customer', 'seller', 'payments.paymentMethod', 'convertedToSale', 'originQuote'])->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->integer('seller_id'));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->value());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->value());
        }

        // Sem filtro de data explícito, a listagem de vendas (não orçamentos)
        // mostra só o dia atual por padrão - pedido do cliente (2026-08-03),
        // pra não paginar o histórico inteiro toda vez que abre a tela.
        // `created_at` já é gravado em horário local (APP_TIMEZONE), então
        // `today()` bate direto com o valor persistido, sem conversão de fuso.
        if (! $request->filled('date_from') && ! $request->filled('date_to') && ! $request->boolean('is_quote')) {
            $query->whereDate('created_at', today());
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->has('is_quote')) {
            $request->boolean('is_quote')
                ? $query->whereNull('cash_register_id')
                : $query->whereNotNull('cash_register_id');
        }

        return SaleResource::collection($query->paginate(20));
    }

    public function show(Sale $sale): SaleResource
    {
        return SaleResource::make($sale->load(['items.productVariation.product', 'customer', 'seller', 'payments.paymentMethod', 'cashRegister', 'convertedToSale', 'originQuote']));
    }

    public function store(StoreSaleRequest $request, RegisterSaleAction $action): SaleResource
    {
        $this->authorize('create', Sale::class);

        $sale = $action->execute($request->validated(), $request->user());

        return SaleResource::make($sale);
    }

    public function cancel(CancelSaleRequest $request, Sale $sale, CancelSaleAction $action): SaleResource
    {
        $this->authorize('cancel', $sale);

        $sale = $action->execute($sale, $request->validated('reason'), $request->user());

        return SaleResource::make($sale);
    }
}
