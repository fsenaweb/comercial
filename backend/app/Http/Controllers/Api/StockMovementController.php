<?php

namespace App\Http\Controllers\Api;

use App\Actions\Stock\AdjustStockAction;
use App\Actions\Stock\RegisterStockEntryAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AdjustStockRequest;
use App\Http\Requests\Stock\StoreStockEntryRequest;
use App\Http\Resources\StockMovementResource;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class StockMovementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = StockMovement::query()
            ->with(['productVariation.product', 'user'])
            ->latest('id');

        if ($request->filled('product_variation_id')) {
            $query->where('product_variation_id', $request->integer('product_variation_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->value());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->whereHas('productVariation', function ($q) use ($search) {
                $q->where('product_code', 'ilike', "%{$search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'ilike', "%{$search}%"));
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->string('date_from')->value());
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->string('date_to')->value());
        }

        return StockMovementResource::collection($query->paginate(30));
    }

    public function adjustment(AdjustStockRequest $request, AdjustStockAction $action): StockMovementResource
    {
        $this->authorize('manage', StockMovement::class);

        $movement = $action->execute($request->validated(), $request->user());

        return StockMovementResource::make($movement->load(['productVariation.product', 'user']));
    }

    public function entry(StoreStockEntryRequest $request, RegisterStockEntryAction $action): StockMovementResource
    {
        $this->authorize('manage', StockMovement::class);

        $movement = $action->execute($request->validated(), $request->user());

        return StockMovementResource::make($movement->load(['productVariation.product', 'user']));
    }
}
