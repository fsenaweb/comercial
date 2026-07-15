<?php

namespace App\Http\Requests\AccountsPayable;

use Illuminate\Foundation\Http\FormRequest;

class SettlePayableInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'paid_amount' => ['nullable', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'paid_amount.min' => 'O valor pago deve ser maior que zero.',
        ];
    }
}
