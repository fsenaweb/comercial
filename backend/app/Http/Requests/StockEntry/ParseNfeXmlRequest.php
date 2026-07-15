<?php

namespace App\Http\Requests\StockEntry;

use Illuminate\Foundation\Http\FormRequest;

class ParseNfeXmlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'xml' => ['required', 'file', 'mimetypes:text/xml,application/xml', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'xml.required' => 'Selecione o arquivo XML da NF-e.',
            'xml.mimetypes' => 'O arquivo precisa ser um XML válido.',
            'xml.max' => 'O arquivo é grande demais (máximo 5MB).',
        ];
    }
}
