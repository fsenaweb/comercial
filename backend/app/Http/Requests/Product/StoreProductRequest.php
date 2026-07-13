<?php

namespace App\Http\Requests\Product;

use App\Enums\ProductType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(ProductType::class)],
            'active' => ['nullable', 'boolean'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'location' => ['nullable', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'subcategory_id' => ['nullable', 'integer', 'exists:subcategories,id'],
            'brand_id' => ['nullable', 'integer', 'exists:brands,id'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'fiscal_fields' => ['nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do produto.',
            'type.required' => 'Selecione o tipo do produto.',
            'unit_id.required' => 'Selecione a unidade de medida.',
            'unit_id.exists' => 'A unidade selecionada não existe.',
            'category_id.required' => 'Selecione a categoria.',
            'category_id.exists' => 'A categoria selecionada não existe.',
            'subcategory_id.exists' => 'A subcategoria selecionada não existe.',
            'brand_id.exists' => 'A marca selecionada não existe.',
            'supplier_id.exists' => 'O fornecedor selecionado não existe.',
        ];
    }
}
