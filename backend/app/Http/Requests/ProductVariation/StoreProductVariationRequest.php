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
            'product_code' => ['required', 'string', 'max:255', 'unique:product_variations,product_code'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'initial_quantity' => ['required', 'integer'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_code.required' => 'Informe o código do produto.',
            'product_code.unique' => 'Já existe uma variação com este código.',
            'cost_price.required' => 'Informe o preço de custo.',
            'sale_price.required' => 'Informe o preço de venda.',
            'initial_quantity.required' => 'Informe a quantidade inicial em estoque.',
        ];
    }
}
