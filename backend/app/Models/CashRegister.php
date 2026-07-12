<?php

namespace App\Models;

use App\Enums\CashOperationType;
use App\Enums\CashRegisterStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashRegister extends Model
{
    /** @use HasFactory<\Database\Factories\CashRegisterFactory> */
    use HasFactory;

    protected $fillable = [
        'opened_at',
        'opening_amount',
        'status',
        'closed_at',
        'closing_amount',
        'opened_by',
        'closed_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_amount' => 'decimal:2',
            'closing_amount' => 'decimal:2',
            'status' => CashRegisterStatus::class,
        ];
    }

    public function operations(): HasMany
    {
        return $this->hasMany(CashOperation::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Caixa aberto atual da loja, ou null. Conveniência de leitura — a regra
     * de negócio de "só um aberto por vez" é garantida na Action, não aqui.
     */
    public static function current(): ?self
    {
        return static::where('status', CashRegisterStatus::Open)->first();
    }

    /**
     * Saldo esperado: abertura + entradas - saídas. Nunca persistido (o
     * schema não tem coluna pra isso); usa bcmath pra evitar imprecisão
     * de ponto flutuante ao somar decimais.
     */
    public function expectedAmount(): string
    {
        $in = (string) $this->operations()->where('type', CashOperationType::In)->sum('amount');
        $out = (string) $this->operations()->where('type', CashOperationType::Out)->sum('amount');

        return bcadd((string) $this->opening_amount, bcsub($in, $out, 2), 2);
    }
}
