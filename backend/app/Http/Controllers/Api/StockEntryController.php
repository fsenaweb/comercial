<?php

namespace App\Http\Controllers\Api;

use App\Actions\StockEntry\ImportNfeStockEntryAction;
use App\Actions\StockEntry\ParseNfeXmlAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StockEntry\ParseNfeXmlRequest;
use App\Http\Requests\StockEntry\StoreStockEntryRequest;
use App\Http\Resources\StockEntryResource;
use App\Models\ProductVariation;
use App\Models\StockEntry;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockEntryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', StockEntry::class);

        return StockEntryResource::collection(
            StockEntry::query()->with(['supplier'])->latest('id')->get()
        );
    }

    public function show(StockEntry $stockEntry): StockEntryResource
    {
        $this->authorize('manage', StockEntry::class);

        return StockEntryResource::make($stockEntry->load(['supplier', 'stockMovements.productVariation.product', 'generatedAccountsPayable.installments']));
    }

    public function parseXml(ParseNfeXmlRequest $request, ParseNfeXmlAction $action): JsonResponse
    {
        $this->authorize('manage', StockEntry::class);

        $parsed = $action->execute($request->file('xml'));

        $eans = collect($parsed['items'])->pluck('ean')->filter()->unique()->values();
        $matchedVariations = ProductVariation::whereIn('ean_gtin', $eans)->with('product')->get()->keyBy('ean_gtin');

        $parsed['items'] = collect($parsed['items'])->map(function ($item) use ($matchedVariations) {
            $match = $item['ean'] ? $matchedVariations->get($item['ean']) : null;

            $item['matched_variation'] = $match ? [
                'id' => $match->id,
                'product_name' => $match->product->name,
                'product_code' => $match->product_code,
                'markup' => $match->markup,
            ] : null;

            return $item;
        })->all();

        $matchedSupplier = Supplier::where('document', $parsed['emit']['cnpj'])->first();
        $parsed['matched_supplier'] = $matchedSupplier ? [
            'id' => $matchedSupplier->id,
            'name' => $matchedSupplier->trade_name ?? $matchedSupplier->corporate_name,
        ] : null;

        return response()->json(['data' => $parsed]);
    }

    public function store(StoreStockEntryRequest $request, ImportNfeStockEntryAction $action): StockEntryResource
    {
        $this->authorize('manage', StockEntry::class);

        $stockEntry = $action->execute($request->validated(), $request->file('xml'), $request->user());

        return StockEntryResource::make($stockEntry);
    }
}
