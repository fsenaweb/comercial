<?php

namespace App\Http\Requests\StoreSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLabelSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'page_width' => ['required', 'numeric', 'min:1'],
            'page_height' => ['required', 'numeric', 'min:1'],
            'margin_top' => ['required', 'numeric', 'min:0'],
            'margin_bottom' => ['required', 'numeric', 'min:0'],
            'margin_left' => ['required', 'numeric', 'min:0'],
            'margin_right' => ['required', 'numeric', 'min:0'],
            'columns' => ['required', 'integer', 'min:1'],
            'rows' => ['required', 'integer', 'min:1'],
            'label_width' => ['required', 'numeric', 'min:1'],
            'label_height' => ['required', 'numeric', 'min:1'],
            'content_fields' => ['required', 'array'],
            'content_fields.name' => ['required', 'boolean'],
            'content_fields.price' => ['required', 'boolean'],
            'content_fields.code' => ['required', 'boolean'],
            'content_fields.barcode' => ['required', 'boolean'],
            'content_fields.store_name' => ['required', 'boolean'],
            'font_sizes' => ['required', 'array'],
            'font_sizes.name' => ['required', 'integer', 'min:6', 'max:24'],
            'font_sizes.price' => ['required', 'integer', 'min:6', 'max:24'],
            'font_sizes.barcode' => ['required', 'integer', 'min:6', 'max:24'],
        ];
    }

    public function messages(): array
    {
        return [
            'required' => 'Este campo é obrigatório.',
            'numeric' => 'Informe um valor numérico.',
            'integer' => 'Informe um número inteiro.',
            'min' => 'Valor abaixo do mínimo permitido.',
            'max' => 'Valor acima do máximo permitido.',
        ];
    }
}
