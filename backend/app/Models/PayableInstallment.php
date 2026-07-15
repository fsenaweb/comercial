<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayableInstallment extends Model
{
    /** @use HasFactory<\Database\Factories\PayableInstallmentFactory> */
    use HasFactory;

    protected $fillable = [
        'accounts_payable_id',
        'number',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'paid_amount',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'status' => InstallmentStatus::class,
            'paid_at' => 'datetime',
            'paid_amount' => 'decimal:2',
        ];
    }

    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
