<?php

namespace App\Http\Requests\AccountsPayable;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountsPayableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'description' => ['required', 'string', 'max:255'],
            'total_amount' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*.number' => ['required', 'integer', 'min:1'],
            'installments.*.amount' => ['required', 'numeric', 'min:0.01'],
            'installments.*.due_date' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required' => 'Selecione o fornecedor.',
            'supplier_id.exists' => 'Fornecedor não encontrado.',
            'description.required' => 'Informe a descrição/origem da conta.',
            'total_amount.required' => 'Informe o valor total.',
            'total_amount.min' => 'O valor total deve ser maior que zero.',
            'installments.required' => 'Informe ao menos uma parcela.',
            'installments.min' => 'Informe ao menos uma parcela.',
            'installments.*.number.required' => 'Informe o número da parcela.',
            'installments.*.amount.required' => 'Informe o valor da parcela.',
            'installments.*.amount.min' => 'O valor da parcela deve ser maior que zero.',
            'installments.*.due_date.required' => 'Informe o vencimento da parcela.',
        ];
    }
}
