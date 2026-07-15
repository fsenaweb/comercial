<?php

namespace App\Models;

use App\Enums\AccountsPayableStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AccountsPayable extends Model
{
    /** @use HasFactory<\Database\Factories\AccountsPayableFactory> */
    use HasFactory, SoftDeletes;

    protected $table = 'accounts_payable';

    protected $fillable = [
        'supplier_id',
        'description',
        'total_amount',
        'installments_count',
        'status',
        'stock_entry_id',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'status' => AccountsPayableStatus::class,
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PayableInstallment::class);
    }

    public function stockEntry(): BelongsTo
    {
        return $this->belongsTo(StockEntry::class, 'stock_entry_id');
    }
}
