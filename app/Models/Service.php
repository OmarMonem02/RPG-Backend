<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'category_id',
        'name',
        'price',
        'max_discount_type',
        'max_discount_value',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
        ];
    }
}
