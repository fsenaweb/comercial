<?php

namespace App\Http\Requests\AccountsReceivable;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountDebitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'discount_type' => ['nullable', 'in:fixed,percentage'],
            // Sem 'min:0': valor negativo é acréscimo (soma em vez de subtrair,
            // ver ResolvesDiscounts::resolveDiscountAmount) - livre, sem teto,
            // decisão do cliente (2026-08-03).
            'discount_value' => ['nullable', 'numeric'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:account_entry_items,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'],
            'items.*.discount_value' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Informe os itens da compra.',
            'items.*.id.required' => 'Item inválido.',
            'items.*.id.exists' => 'Item não encontrado.',
        ];
    }
}
