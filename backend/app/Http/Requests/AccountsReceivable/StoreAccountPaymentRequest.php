<?php

namespace App\Http\Requests\AccountsReceivable;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['required', 'integer', 'exists:payment_methods,id'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.required' => 'Informe o valor pago.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'payment_method_id.required' => 'Selecione a forma de pagamento.',
            'payment_method_id.exists' => 'Forma de pagamento não encontrada.',
        ];
    }
}
