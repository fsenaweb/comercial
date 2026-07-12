<?php

namespace App\Http\Requests\CashRegister;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCashRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'opening_amount' => ['required', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'opening_amount.required' => 'Informe o valor de abertura do caixa.',
            'opening_amount.numeric' => 'O valor de abertura deve ser numérico.',
            'opening_amount.min' => 'O valor de abertura não pode ser negativo.',
        ];
    }
}
