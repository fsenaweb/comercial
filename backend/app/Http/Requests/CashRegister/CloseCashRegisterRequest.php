<?php

namespace App\Http\Requests\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'closing_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'closing_amount.required' => 'Informe o valor de fechamento do caixa.',
            'closing_amount.numeric' => 'O valor de fechamento deve ser numérico.',
            'closing_amount.min' => 'O valor de fechamento não pode ser negativo.',
        ];
    }
}
