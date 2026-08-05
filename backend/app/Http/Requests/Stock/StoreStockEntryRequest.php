<?php

namespace App\Http\Requests\Stock;

use Illuminate\Foundation\Http\FormRequest;

class StoreStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->input('origin') === '') {
            $this->merge(['origin' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'product_variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'origin' => ['nullable', 'string', 'max:255'],
            // Só admin pode atualizar preço na mesma entrada (ver
            // ProductVariationPolicy::update) - a Action ignora esses campos
            // se quem enviou não for admin, então a validação aqui é só
            // formato; a autorização de fato é checada na Action.
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'markup' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'product_variation_id.required' => 'Selecione o produto.',
            'product_variation_id.exists' => 'Produto não encontrado.',
            'quantity.required' => 'Informe a quantidade recebida.',
            'quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
