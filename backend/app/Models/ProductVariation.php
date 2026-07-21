<?php

namespace App\Models;

use Database\Factories\ProductVariationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariation extends Model
{
    /** @use HasFactory<ProductVariationFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_id',
        'color',
        'size',
        'ean_gtin',
        'product_code',
        'legacy_code',
        'cost_price',
        'markup',
        'sale_price',
        'current_quantity',
        'min_quantity',
        'max_quantity',
        'wholesale_min_qty',
        'wholesale_price',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'markup' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'current_quantity' => 'integer',
            'min_quantity' => 'integer',
            'max_quantity' => 'integer',
            'wholesale_min_qty' => 'integer',
            'wholesale_price' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->whereNotNull('min_quantity')
            ->whereColumn('current_quantity', '<', 'min_quantity');
    }
}
