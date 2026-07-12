<?php

namespace App\Http\Requests\StoreSetting;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStoreSettingLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'Selecione uma imagem para o logotipo.',
            'logo.image' => 'O arquivo precisa ser uma imagem.',
            'logo.mimes' => 'Envie o logotipo em PNG ou JPG.',
            'logo.max' => 'O logotipo deve ter até 2MB.',
        ];
    }
}
