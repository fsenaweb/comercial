<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\RegisterSaleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SaleController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Sale::query()->with(['customer', 'seller', 'paymentMethod'])->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('number', 'ilike', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%"));
            });
        }

        return SaleResource::collection($query->paginate(20));
    }

    public function show(Sale $sale): SaleResource
    {
        return SaleResource::make($sale->load(['items.productVariation.product', 'customer', 'seller', 'paymentMethod', 'cashRegister']));
    }

    public function store(StoreSaleRequest $request, RegisterSaleAction $action): SaleResource
    {
        $this->authorize('create', Sale::class);

        $sale = $action->execute($request->validated(), $request->user());

        return SaleResource::make($sale);
    }
}
