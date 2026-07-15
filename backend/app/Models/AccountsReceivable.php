<?php

namespace App\Models;

use App\Enums\AccountEntryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsReceivable extends Model
{
    /** @use HasFactory<\Database\Factories\AccountsReceivableFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_receivable';

    protected $fillable = [
        'customer_id',
        'notes',
        'created_by',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(AccountEntry::class);
    }

    /**
     * Saldo devedor = soma das compras menos soma dos pagamentos. Nunca
     * persistido — a conta corrente não tem "fechamento", é sempre
     * recalculada a partir do extrato (mesma filosofia de
     * `CashRegister::expectedAmount()`).
     */
    public function balance(): string
    {
        $purchases = (string) $this->entries()->where('type', AccountEntryType::Purchase)->sum('amount');
        $payments = (string) $this->entries()->where('type', AccountEntryType::Payment)->sum('amount');

        return bcsub($purchases, $payments, 2);
    }
}
