<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seller extends Model
{
    use HasFactory;

    public const COMMISSION_TYPE_TOTAL = 'total';
    public const COMMISSION_TYPE_PROFIT = 'profit';

    protected $fillable = [
        'name',
        'commission_type',
        'commission_value',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
