<?php

namespace App\Http\Requests\CashRegister;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashOperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'origin' => ['required', Rule::in(['cash_withdrawal', 'cash_reinforcement'])],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method_id' => ['nullable', 'integer', 'exists:payment_methods,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'origin.required' => 'Selecione o tipo de lançamento (sangria ou reforço).',
            'origin.in' => 'Tipo de lançamento inválido.',
            'amount.required' => 'Informe o valor do lançamento.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'payment_method_id.exists' => 'A forma de pagamento selecionada não existe.',
        ];
    }
}
