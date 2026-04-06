<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    use HasFactory;

    public const USD = 'USD';

    protected $fillable = [
        'currency',
        'rate',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
        ];
    }
}
