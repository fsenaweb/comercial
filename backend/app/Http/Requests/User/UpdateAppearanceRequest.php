<?php

namespace App\Http\Requests\User;

use App\Enums\FontScale;
use App\Enums\Theme;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'theme' => ['required', Rule::enum(Theme::class)],
            'font_scale' => ['required', Rule::enum(FontScale::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'theme.required' => 'Selecione o tema.',
            'theme.enum' => 'Tema inválido.',
            'font_scale.required' => 'Selecione o tamanho de fonte.',
            'font_scale.enum' => 'Tamanho de fonte inválido.',
        ];
    }
}
