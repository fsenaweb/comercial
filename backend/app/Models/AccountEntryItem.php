<?php

namespace App\Models;

use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountEntryItem extends Model
{
    /** @use HasFactory<\Database\Factories\AccountEntryItemFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = [
        'account_entry_id',
        'product_variation_id',
        'quantity',
        'unit_price',
        'discount_type',
        'discount_value',
        'discount',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_price' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
            'discount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function accountEntry(): BelongsTo
    {
        return $this->belongsTo(AccountEntry::class);
    }

    public function productVariation(): BelongsTo
    {
        return $this->belongsTo(ProductVariation::class);
    }
}
