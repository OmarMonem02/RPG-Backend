<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalRequest extends Model
{
    public const TYPE_SALE_DISCOUNT = 'sale_discount';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_CONSUMED = 'consumed';

    protected $fillable = [
        'type',
        'status',
        'requested_by',
        'reviewed_by',
        'reviewed_at',
        'requested_discount_amount',
        'approved_discount_amount',
        'discount_input_type',
        'discount_input_value',
        'approved_discount_input_type',
        'approved_discount_input_value',
        'cart_subtotal',
        'rejection_reason',
        'payload',
        'consumed_at',
        'consumed_sale_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_discount_amount' => 'float',
            'approved_discount_amount' => 'float',
            'discount_input_value' => 'float',
            'approved_discount_input_value' => 'float',
            'cart_subtotal' => 'float',
            'payload' => 'array',
            'reviewed_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function consumedSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'consumed_sale_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isConsumable(): bool
    {
        return $this->isApproved() && $this->consumed_at === null;
    }
}
