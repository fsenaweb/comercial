<?php

namespace App\Models;

use App\Enums\CashOperationOrigin;
use App\Enums\CashOperationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashOperation extends Model
{
    /** @use HasFactory<\Database\Factories\CashOperationFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'cash_register_id',
        'user_id',
        'type',
        'origin',
        'reference_id',
        'payment_method_id',
        'amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'type' => CashOperationType::class,
            'origin' => CashOperationOrigin::class,
            'amount' => 'decimal:2',
        ];
    }

    public function cashRegister(): BelongsTo
    {
        return $this->belongsTo(CashRegister::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'reference_id');
    }
}
