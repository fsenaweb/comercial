<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'origin' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variation_id.required' => 'Selecione o produto.',
            'product_variation_id.exists' => 'Produto não encontrado.',
            'quantity.required' => 'Informe a quantidade recebida.',
            'quantity.min' => 'A quantidade deve ser maior que zero.',
            'origin.required' => 'Informe a origem da entrada (ex.: fornecedor, nota fiscal).',
        ];
    }
}
