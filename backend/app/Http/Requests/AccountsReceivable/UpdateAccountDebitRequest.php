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
            'discount_value' => ['nullable', 'numeric', 'min:0'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:account_entry_items,id'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount_type' => ['nullable', 'in:fixed,percentage'],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
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
