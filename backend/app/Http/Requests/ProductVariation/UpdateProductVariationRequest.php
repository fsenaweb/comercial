<?php

namespace App\Http\Requests\ProductVariation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductVariationRequest extends FormRequest
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
            'product_code' => [
                'required', 'string', 'max:255',
                Rule::unique('product_variations', 'product_code')->ignore($this->route('productVariation')),
            ],
            'legacy_code' => ['nullable', 'string', 'max:255'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['required', 'numeric', 'min:0'],
            'min_quantity' => ['nullable', 'integer', 'min:0'],
            'max_quantity' => ['nullable', 'integer', 'min:0'],
            'wholesale_min_qty' => ['nullable', 'integer', 'min:1', 'required_with:wholesale_price'],
            'wholesale_price' => ['nullable', 'numeric', 'min:0', 'required_with:wholesale_min_qty'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_code.required' => 'Informe o código do produto.',
            'product_code.unique' => 'Já existe uma variação com este código.',
            'cost_price.required' => 'Informe o preço de custo.',
            'sale_price.required' => 'Informe o preço de venda.',
            'wholesale_min_qty.required_with' => 'Informe a quantidade mínima para o preço de atacado.',
            'wholesale_price.required_with' => 'Informe o preço de atacado.',
        ];
    }
}
