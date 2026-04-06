<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleItem extends Model
{
    use HasFactory;

    public const ITEM_TYPE_PRODUCT = 'product';
    public const ITEM_TYPE_BIKE = 'bike';

    protected $fillable = [
        'sale_id',
        'item_type',
        'item_id',
        'price_snapshot',
        'qty',
        'discount',
    ];

    protected $appends = [
        'line_total',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'discount' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function getLineTotalAttribute(): float
    {
        return max(round(((float) $this->price_snapshot * $this->qty) - (float) $this->discount, 2), 0);
    }
}
