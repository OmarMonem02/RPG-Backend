<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const TYPE_PART = 'part';
    public const TYPE_ACCESSORY = 'accessory';

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';
    public const DISCOUNT_TYPE_FIXED = 'fixed';

    protected $fillable = [
        'type',
        'name',
        'sku',
        'part_number',
        'category_id',
        'brand_id',
        'qty',
        'cost_price',
        'selling_price',
        'cost_price_usd',
        'max_discount_type',
        'max_discount_value',
        'is_universal',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
            'cost_price_usd' => 'decimal:2',
            'max_discount_value' => 'decimal:2',
            'is_universal' => 'boolean',
        ];
    }
}
