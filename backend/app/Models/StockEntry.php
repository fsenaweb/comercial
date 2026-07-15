<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockEntry extends Model
{
    /** @use HasFactory<\Database\Factories\StockEntryFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'nfe_number',
        'nfe_series',
        'nfe_key',
        'issue_date',
        'freight_value',
        'products_total',
        'total_value',
        'xml_path',
        'generated_accounts_payable_id',
        'imported_by',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'freight_value' => 'decimal:2',
            'products_total' => 'decimal:2',
            'total_value' => 'decimal:2',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'reference_id')->where('origin', 'stock_entry');
    }

    public function generatedAccountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class, 'generated_accounts_payable_id');
    }
}
