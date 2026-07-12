<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethod\StorePaymentMethodRequest;
use App\Http\Requests\PaymentMethod\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PaymentMethodController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PaymentMethodResource::collection(PaymentMethod::query()->orderBy('name')->get());
    }

    public function store(StorePaymentMethodRequest $request): PaymentMethodResource
    {
        $this->authorize('create', PaymentMethod::class);

        return PaymentMethodResource::make(PaymentMethod::create($request->validated()));
    }

    public function show(PaymentMethod $paymentMethod): PaymentMethodResource
    {
        return PaymentMethodResource::make($paymentMethod);
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): PaymentMethodResource
    {
        $this->authorize('update', $paymentMethod);

        $paymentMethod->update($request->validated());

        return PaymentMethodResource::make($paymentMethod);
    }

    public function destroy(PaymentMethod $paymentMethod): \Illuminate\Http\Response
    {
        $this->authorize('delete', $paymentMethod);

        $paymentMethod->delete();

        return response()->noContent();
    }
}
