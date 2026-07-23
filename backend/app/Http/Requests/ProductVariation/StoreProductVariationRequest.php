<?php

namespace App\Http\Requests\ProductVariation;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductVariationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'color' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:255'],
            'ean_gtin' => ['nullable', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:255', 'unique:product_variations,code'],
            'reference' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'initial_quantity' => ['required', 'integer'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'wholesale_min_qty' => ['nullable', 'integer', 'min:1', 'required_with:wholesale_price'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'required_with:wholesale_min_qty'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Informe o código do produto.',
            'code.unique' => 'Já existe uma variação com este código.',
            'cost_price.required' => 'Informe o preço de custo.',
            'sale_price.required' => 'Informe o preço de venda.',
            'initial_quantity.required' => 'Informe a quantidade inicial em estoque.',
            'wholesale_min_qty.required_with' => 'Informe a quantidade mínima para o preço de atacado.',
            'wholesale_price.required_with' => 'Informe o preço de atacado.',
        ];
    }
}
