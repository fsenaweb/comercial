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
            'payment_method_id' => [
                'required', 'integer',
                Rule::exists('payment_methods', 'id')->where('active_on_pos', true),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'payment_method_id.required' => 'Selecione a forma de pagamento.',
            'payment_method_id.exists' => 'A forma de pagamento selecionada não está disponível no PDV.',
        ];
    }
}
