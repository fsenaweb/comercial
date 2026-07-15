<?php

namespace App\Models;

use App\Enums\AccountEntryType;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountEntry extends Model
{
    /** @use HasFactory<\Database\Factories\AccountEntryFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'accounts_receivable_id',
        'type',
        'subtotal',
        'discount_type',
        'discount_value',
        'discount',
        'amount',
        'description',
        'payment_method_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountEntryType::class,
            'subtotal' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function accountsReceivable(): BelongsTo
    {
        return $this->belongsTo(AccountsReceivable::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AccountEntryItem::class);
    }
}
