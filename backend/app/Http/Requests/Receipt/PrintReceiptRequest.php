<?php

namespace App\Http\Requests\Receipt;

use Illuminate\Foundation\Http\FormRequest;

class PrintReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'format' => ['sometimes', 'string', 'in:roll80,roll58,a4'],
        ];
    }

    public function messages(): array
    {
        return [
            'format.in' => 'Formato de impressão inválido.',
        ];
    }
}
