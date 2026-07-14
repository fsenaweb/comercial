<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sale\ConvertQuoteToSaleAction;
use App\Actions\Sale\RegisterQuoteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\ConvertQuoteRequest;
use App\Http\Requests\Sale\StoreQuoteRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;

class QuoteController extends Controller
{
    public function store(StoreQuoteRequest $request, RegisterQuoteAction $action): SaleResource
    {
        $this->authorize('create', Sale::class);

        $quote = $action->execute($request->validated(), $request->user());

        return SaleResource::make($quote);
    }

    public function convert(ConvertQuoteRequest $request, Sale $sale, ConvertQuoteToSaleAction $action): SaleResource
    {
        $this->authorize('convert', $sale);

        $newSale = $action->execute($sale, $request->validated(), $request->user());

        return SaleResource::make($newSale);
    }
}
