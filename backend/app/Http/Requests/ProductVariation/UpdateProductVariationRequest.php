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
            'cost_price' => ['required', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric'],
            'sale_price' => ['required', 'numeric', 'min:0'],
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
        ];
    }
}
