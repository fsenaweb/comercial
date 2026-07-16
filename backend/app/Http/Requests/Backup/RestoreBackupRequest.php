<?php

namespace App\Http\Requests\Backup;

use Illuminate\Foundation\Http\FormRequest;

class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // O valor em si (bater com o código gerado por
            // GenerateRestoreConfirmationCodeAction) é conferido no
            // controller, não aqui — exige Cache::pull() atômico por usuário.
            'confirmation' => ['required', 'string'],
            'filename' => ['nullable', 'string', 'required_without:file'],
            'file' => ['nullable', 'file', 'mimes:zip', 'required_without:filename'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation.required' => 'Digite o código de confirmação exibido na tela.',
            'filename.required_without' => 'Escolha um backup local ou envie um arquivo.',
            'file.required_without' => 'Escolha um backup local ou envie um arquivo.',
            'file.mimes' => 'O arquivo precisa ser um .zip de backup.',
        ];
    }
}
