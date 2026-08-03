<?php

namespace App\Http\Requests\AccountsReceivable;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'description' => ['required', 'string', 'max:255'],
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            // Sem 'min:0': valor negativo é acréscimo (soma em vez de subtrair,
            // ver ResolvesDiscounts::resolveDiscountAmount) - livre, sem teto,
            // decisão do cliente (2026-08-03).
            'discount_value' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'],
            'items.*.discount_value' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_id.required' => 'Selecione o cliente.',
            'customer_id.exists' => 'Cliente não encontrado.',
            'description.required' => 'Descreva a compra (ex.: data, referência).',
            'items.required' => 'Adicione ao menos um produto.',
            'items.min' => 'Adicione ao menos um produto.',
            'items.*.product_variation_id.required' => 'Selecione o produto.',
            'items.*.product_variation_id.exists' => 'Produto não encontrado.',
            'items.*.quantity.required' => 'Informe a quantidade.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
