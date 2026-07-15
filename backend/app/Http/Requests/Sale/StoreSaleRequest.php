<?php

namespace App\Http\Requests\Sale;

use App\Models\StoreSetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSaleRequest extends FormRequest
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
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => [
                'required', 'integer',
                Rule::exists('payment_methods', 'id')->where('active_on_pos', true),
            ],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'discount_type' => ['nullable', Rule::in(['fixed', 'percentage'])],
            'discount_value' => [
                'nullable', 'numeric', 'min:0',
                Rule::when(($this->input('discount_type') ?? 'fixed') === 'percentage', ['max:100']),
            ],
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
            'seller_id.required' => 'Selecione o vendedor responsável pela venda.',
            'payments.required' => 'Adicione ao menos uma forma de pagamento.',
            'payments.min' => 'Adicione ao menos uma forma de pagamento.',
            'payments.*.payment_method_id.required' => 'Selecione a forma de pagamento.',
            'payments.*.payment_method_id.exists' => 'Uma das formas de pagamento selecionadas não está disponível no PDV.',
            'payments.*.amount.required' => 'Informe o valor de cada forma de pagamento.',
            'payments.*.amount.min' => 'O valor de cada forma de pagamento deve ser maior que zero.',
            'discount_value.max' => 'O desconto percentual não pode ser maior que 100%.',
            'items.required' => 'Adicione ao menos um item à venda.',
            'items.min' => 'Adicione ao menos um item à venda.',
            'items.*.product_variation_id.exists' => 'Um dos produtos da venda não foi encontrado.',
            'items.*.quantity.min' => 'A quantidade deve ser maior que zero.',
        ];
    }
}
