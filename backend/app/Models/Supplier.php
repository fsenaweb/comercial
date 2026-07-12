<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'corporate_name',
        'trade_name',
        'mobile_phone',
        'phone',
        'email',
        'document',
        'is_company',
        'state_registration',
        'address',
        'zip_code',
        'address_number',
        'address_complement',
        'neighborhood',
        'city',
        'state',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_company' => 'boolean',
        ];
    }
}
