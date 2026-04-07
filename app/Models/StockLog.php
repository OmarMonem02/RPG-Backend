<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLog extends Model
{
    use HasFactory;

    public const CHANGE_TYPE_ADD = 'add';

    public const CHANGE_TYPE_REDUCE = 'reduce';

    public const CHANGE_TYPE_RETURN = 'return';

    protected $fillable = [
        'product_id',
        'type',
        'change_type',
        'qty',
        'qty_before',
        'qty_after',
        'reference_type',
        'reference_id',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'qty' => 'decimal:4',
            'qty_before' => 'decimal:4',
            'qty_after' => 'decimal:4',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
