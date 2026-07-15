<?php

namespace App\Http\Controllers\Api;

use App\Actions\AccountsPayable\RegisterAccountsPayableAction;
use App\Actions\AccountsPayable\SettlePayableInstallmentAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountsPayable\SettlePayableInstallmentRequest;
use App\Http\Requests\AccountsPayable\StoreAccountsPayableRequest;
use App\Http\Resources\AccountsPayableResource;
use App\Http\Resources\PayableInstallmentResource;
use App\Models\AccountsPayable;
use App\Models\PayableInstallment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountsPayableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', AccountsPayable::class);

        $query = AccountsPayable::query()->with(['supplier', 'installments'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ilike', "%{$search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('corporate_name', 'ilike', "%{$search}%")->orWhere('trade_name', 'ilike', "%{$search}%"));
            });
        }

        return AccountsPayableResource::collection($query->get());
    }

    public function show(AccountsPayable $accountsPayable): AccountsPayableResource
    {
        $this->authorize('manage', AccountsPayable::class);

        return AccountsPayableResource::make($accountsPayable->load(['supplier', 'installments', 'createdBy']));
    }

    public function store(StoreAccountsPayableRequest $request, RegisterAccountsPayableAction $action): AccountsPayableResource
    {
        $this->authorize('manage', AccountsPayable::class);

        $accountsPayable = $action->execute($request->validated(), $request->user());

        return AccountsPayableResource::make($accountsPayable);
    }

    public function settleInstallment(SettlePayableInstallmentRequest $request, PayableInstallment $payableInstallment, SettlePayableInstallmentAction $action): PayableInstallmentResource
    {
        $this->authorize('manage', AccountsPayable::class);

        $payableInstallment = $action->execute($payableInstallment, $request->validated(), $request->user());

        return PayableInstallmentResource::make($payableInstallment);
    }
}
