<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Semente mínima para começar a operar: sem um admin, ninguém entra no
     * sistema. Credenciais de dev — trocar a senha em qualquer ambiente real.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@loja.local'],
            [
                'name' => 'Administrador',
                'password' => 'password',
                'role' => UserRole::Admin,
                'active' => true,
            ],
        );

        $this->call(PaymentMethodSeeder::class);
    }
}
