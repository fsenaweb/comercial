<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    /** @use HasFactory<\Database\Factories\StoreSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'trade_name',
        'cnpj',
        'email',
        'phone',
        'mobile_phone',
        'zip_code',
        'address',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'logo_path',
        'require_seller_on_sale',
        'auto_open_cash_register',
        'label_settings',
    ];

    protected function casts(): array
    {
        return [
            'require_seller_on_sale' => 'boolean',
            'auto_open_cash_register' => 'boolean',
            'label_settings' => 'array',
        ];
    }

    /**
     * A loja tem sempre um único registro de configuração (id fixo = 1).
     * Cria com valores padrão na primeira leitura, se ainda não existir.
     */
    public static function current(): self
    {
        $settings = static::find(1);

        if (! $settings) {
            // Defaults explícitos aqui (não só na migration): save() não
            // recarrega os defaults de coluna no objeto em memória, então sem
            // isso a resposta traria null em vez de false logo após a criação.
            $settings = new static([
                'name' => 'Minha Loja',
                'require_seller_on_sale' => false,
                'auto_open_cash_register' => false,
            ]);
            // 'id' não é mass-assignable (por design); setado à parte para
            // garantir o registro único sempre em id=1, independente do
            // estado da sequence do Postgres (que não é transacional).
            $settings->id = 1;
            $settings->save();
        }

        // Provisionamento implícito do registro único não é uma "criação" do
        // ponto de vista da API — evita que JsonResource devolva 201 num GET/PUT.
        $settings->wasRecentlyCreated = false;

        return $settings;
    }
}
