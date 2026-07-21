<?php

namespace App\Http\Requests\User;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('user'))],
            // Em branco = mantém a senha atual (ver UpdateUserAction, que remove a
            // chave do payload quando vazia em vez de sobrescrever com null).
            'password' => ['nullable', 'string'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'commission_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'active' => ['required', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe um usuário com esse e-mail.',
            'role.required' => 'Selecione o papel do usuário.',
        ];
    }
}
