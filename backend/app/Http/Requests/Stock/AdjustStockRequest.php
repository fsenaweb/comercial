<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'new_quantity' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variation_id.required' => 'Selecione o produto.',
            'product_variation_id.exists' => 'Produto não encontrado.',
            'new_quantity.required' => 'Informe a quantidade contada em estoque.',
            'new_quantity.min' => 'A quantidade em estoque não pode ser negativa.',
            'reason.required' => 'Informe o motivo do ajuste.',
        ];
    }
}
