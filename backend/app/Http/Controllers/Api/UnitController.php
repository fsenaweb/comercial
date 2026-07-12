<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unit\StoreUnitRequest;
use App\Http\Requests\Unit\UpdateUnitRequest;
use App\Http\Resources\UnitResource;
use App\Models\Unit;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UnitController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return UnitResource::collection(Unit::orderBy('name')->get());
    }

    public function store(StoreUnitRequest $request): UnitResource
    {
        $this->authorize('create', Unit::class);

        return UnitResource::make(Unit::create($request->validated()));
    }

    public function show(Unit $unit): UnitResource
    {
        return UnitResource::make($unit);
    }

    public function update(UpdateUnitRequest $request, Unit $unit): UnitResource
    {
        $this->authorize('update', $unit);

        $unit->update($request->validated());

        return UnitResource::make($unit);
    }

    public function destroy(Unit $unit): \Illuminate\Http\Response
    {
        $this->authorize('delete', $unit);

        $unit->delete();

        return response()->noContent();
    }
}
