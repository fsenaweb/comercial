<?php

namespace App\Http\Controllers\Api;

use App\Actions\AccountsReceivable\RegisterAccountDebitAction;
use App\Actions\AccountsReceivable\RegisterAccountPaymentAction;
use App\Actions\AccountsReceivable\UpdateAccountDebitAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\AccountsReceivable\StoreAccountDebitRequest;
use App\Http\Requests\AccountsReceivable\StoreAccountPaymentRequest;
use App\Http\Requests\AccountsReceivable\UpdateAccountDebitRequest;
use App\Http\Resources\AccountEntryResource;
use App\Http\Resources\AccountsReceivableResource;
use App\Models\AccountEntry;
use App\Models\AccountsReceivable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AccountsReceivableController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', AccountsReceivable::class);

        $query = AccountsReceivable::query()->with(['customer', 'entries'])->latest('id');

        if ($request->filled('search')) {
            $search = $request->string('search')->value();
            $query->whereHas('customer', fn ($c) => $c->where('name', 'ilike', "%{$search}%"));
        }

        return AccountsReceivableResource::collection($query->get());
    }

    public function show(AccountsReceivable $accountsReceivable): AccountsReceivableResource
    {
        $this->authorize('manage', AccountsReceivable::class);

        return AccountsReceivableResource::make(
            $accountsReceivable->load(['customer', 'entries.paymentMethod', 'entries.createdBy', 'entries.items.productVariation.product'])
        );
    }

    public function storeDebit(StoreAccountDebitRequest $request, RegisterAccountDebitAction $action): AccountEntryResource
    {
        $this->authorize('manage', AccountsReceivable::class);

        $entry = $action->execute($request->validated(), $request->user());

        return AccountEntryResource::make($entry);
    }

    public function updateDebit(UpdateAccountDebitRequest $request, AccountEntry $accountEntry, UpdateAccountDebitAction $action): AccountEntryResource
    {
        $this->authorize('manage', AccountsReceivable::class);

        $entry = $action->execute($accountEntry, $request->validated());

        return AccountEntryResource::make($entry);
    }

    public function storePayment(StoreAccountPaymentRequest $request, AccountsReceivable $accountsReceivable, RegisterAccountPaymentAction $action): AccountEntryResource
    {
        $this->authorize('manage', AccountsReceivable::class);

        $entry = $action->execute($accountsReceivable, $request->validated(), $request->user());

        return AccountEntryResource::make($entry);
    }
}
