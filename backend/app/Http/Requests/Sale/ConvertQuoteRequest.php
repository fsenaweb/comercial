<?php

namespace App\Http\Requests\Sale;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertQuoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.payment_method_id' => [
                'required', 'integer',
                Rule::exists('payment_methods', 'id')->where('active_on_pos', true),
            ],
            'payments.*.amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }

    public function messages(): array
    {
        return [
            'payments.required' => 'Adicione ao menos uma forma de pagamento.',
            'payments.min' => 'Adicione ao menos uma forma de pagamento.',
            'payments.*.payment_method_id.required' => 'Selecione a forma de pagamento.',
            'payments.*.payment_method_id.exists' => 'Uma das formas de pagamento selecionadas não está disponível no PDV.',
            'payments.*.amount.required' => 'Informe o valor de cada forma de pagamento.',
            'payments.*.amount.min' => 'O valor de cada forma de pagamento deve ser maior que zero.',
        ];
    }
}
