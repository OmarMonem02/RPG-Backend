<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SaleAdjustment extends Model
{
    protected $fillable = [
        'sale_id',
        'user_id',
        'action_type',
        'summary',
        'before_snapshot',
        'after_snapshot',
        'amount_delta',
        'refund_amount',
        'extra_amount_due',
        'notes',
        'meta',
    ];

    protected $casts = [
        'before_snapshot' => 'array',
        'after_snapshot' => 'array',
        'meta' => 'array',
        'amount_delta' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'extra_amount_due' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
