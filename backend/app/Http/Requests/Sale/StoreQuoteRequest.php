<?php

namespace App\Http\Requests\Sale;

use App\Models\StoreSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $requireSeller = StoreSetting::current()->require_seller_on_sale;

        return [
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'seller_id' => [$requireSeller ? 'required' : 'nullable', 'integer', 'exists:users,id'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => [
                'nullable', 'numeric', 'min:0',
                Rule::when(($this->input('discount_type') ?? 'fixed') === 'percentage', ['max:100']),
            ],
            'admin_password' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_variation_id' => [
                'required', 'integer',
                Rule::exists('product_variations', 'id')->whereNull('deleted_at'),
            ],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.apply_wholesale' => ['nullable', 'boolean'],
            'items.*.discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'items.*.discount_value' => ['nullable', 'numeric', 'min:0'],
            'items.*' => [
                function ($attribute, $value, $fail) {
                    $type = $value['discount_type'] ?? 'fixed';
                    $discountValue = $value['discount_value'] ?? 0;
                    if ($type === 'percentage' && $discountValue > 100) {
                        $fail('O desconto percentual do item não pode ser maior que 100%.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'seller_id.required' => 'Selecione o vendedor responsável pelo orçamento.',
            'expires_at.after_or_equal' => 'A validade não pode ser uma data no passado.',
            'discount_value.max' => 'O desconto percentual não pode ser maior que 100%.',
            'items.required' => 'Adicione ao menos um item ao orçamento.',
            'items.min' => 'Adicione ao menos um item ao orçamento.',
            'items.*.product_variation_id.exists' => 'Um dos produtos do orçamento não foi encontrado.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
