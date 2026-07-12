<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    /** @use HasFactory<\Database\Factories\CustomerFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'mobile_phone',
        'phone',
        'email',
        'document',
        'is_company',
        'birth_date',
        'zip_code',
        'address',
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
            'birth_date' => 'date',
        ];
    }
}
