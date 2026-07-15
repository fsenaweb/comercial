<?php

namespace App\Http\Requests\Expense;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'due_date' => ['required', 'date'],
            'paid_now' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'description.required' => 'Informe a descrição da despesa.',
            'amount.required' => 'Informe o valor.',
            'amount.min' => 'O valor deve ser maior que zero.',
            'due_date.required' => 'Informe o vencimento.',
        ];
    }
}
