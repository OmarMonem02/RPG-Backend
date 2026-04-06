<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleReturn extends Model
{
    use HasFactory;

    protected $table = 'returns';

    protected $fillable = [
        'sale_id',
        'item_id',
        'qty',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'integer',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(SaleItem::class, 'item_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRefundTotalAttribute(): float
    {
        $item = $this->relationLoaded('item') ? $this->item : $this->item()->first();

        if ($item === null || $item->qty <= 0) {
            return 0.0;
        }

        $unitDiscount = round((float) $item->discount / $item->qty, 2);

        return round(((float) $item->price_snapshot * $this->qty) - ($unitDiscount * $this->qty), 2);
    }
}
