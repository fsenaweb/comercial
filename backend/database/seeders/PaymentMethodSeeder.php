<?php

namespace Database\Seeders;

use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'Dinheiro',
            'Cartão de Débito',
            'Cartão de Crédito',
            'Pix',
            'Boleto',
            'Cheque',
            'Crediário',
            'Transferência Bancária',
        ] as $name) {
            PaymentMethod::firstOrCreate(['name' => $name], ['active_on_pos' => true]);
        }
    }
}
