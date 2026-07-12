<?php

namespace App\Http\Controllers\Api;

use App\Actions\CashRegister\CloseCashRegisterAction;
use App\Actions\CashRegister\OpenCashRegisterAction;
use App\Actions\CashRegister\RegisterCashOperationAction;
use App\Actions\CashRegister\RemoveCashOperationAction;
use App\Actions\CashRegister\UpdateCashRegisterAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\CashRegister\CloseCashRegisterRequest;
use App\Http\Requests\CashRegister\OpenCashRegisterRequest;
use App\Http\Requests\CashRegister\StoreCashOperationRequest;
use App\Http\Requests\CashRegister\UpdateCashRegisterRequest;
use App\Http\Resources\CashOperationResource;
use App\Http\Resources\CashRegisterResource;
use App\Models\CashOperation;
use App\Models\CashRegister;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CashRegisterController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = CashRegister::query()->with(['openedBy', 'closedBy'])->latest('opened_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                if (is_numeric($search)) {
                    $q->orWhere('id', (int) $search);
                }
                $q->orWhere('notes', 'ilike', "%{$search}%");
            });
        }

        return CashRegisterResource::collection($query->get());
    }

    public function current(): CashRegisterResource|JsonResponse
    {
        $cashRegister = CashRegister::current();

        if (! $cashRegister) {
            return response()->json(['data' => null]);
        }

        return CashRegisterResource::make($cashRegister->load(['openedBy', 'closedBy']));
    }

    public function open(OpenCashRegisterRequest $request, OpenCashRegisterAction $action): CashRegisterResource
    {
        $this->authorize('operate', CashRegister::class);

        $cashRegister = $action->execute($request->validated(), $request->user());

        return CashRegisterResource::make($cashRegister->load('openedBy'));
    }

    public function update(UpdateCashRegisterRequest $request, CashRegister $cashRegister, UpdateCashRegisterAction $action): CashRegisterResource
    {
        $this->authorize('operate', CashRegister::class);

        $cashRegister = $action->execute($cashRegister, $request->validated());

        return CashRegisterResource::make($cashRegister->load(['openedBy', 'closedBy']));
    }

    public function close(CloseCashRegisterRequest $request, CashRegister $cashRegister, CloseCashRegisterAction $action): CashRegisterResource
    {
        $this->authorize('operate', CashRegister::class);

        $cashRegister = $action->execute($cashRegister, $request->validated(), $request->user());

        return CashRegisterResource::make($cashRegister->load(['openedBy', 'closedBy']));
    }

    public function operations(CashRegister $cashRegister): AnonymousResourceCollection
    {
        return CashOperationResource::collection(
            $cashRegister->operations()->with(['user', 'paymentMethod'])->latest('id')->get()
        );
    }

    public function storeOperation(StoreCashOperationRequest $request, RegisterCashOperationAction $action): CashOperationResource
    {
        $this->authorize('operate', CashRegister::class);

        $operation = $action->execute($request->validated(), $request->user());

        return CashOperationResource::make($operation->load(['user', 'paymentMethod']));
    }

    public function destroyOperation(CashOperation $cashOperation, RemoveCashOperationAction $action): \Illuminate\Http\Response
    {
        $this->authorize('operate', CashRegister::class);

        $action->execute($cashOperation);

        return response()->noContent();
    }
}
