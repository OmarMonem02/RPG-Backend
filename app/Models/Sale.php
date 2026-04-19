<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use SoftDeletes, LogsHistory;

    public const TYPE_SITE = 'site';
    public const TYPE_ONLINE = 'online';
    public const TYPE_DELIVERY = 'delivery';

    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL = 'partial';
    public const STATUS_PENDING = 'pending';

    protected $fillable = [
        'customer_id',
        'user_id',
        'seller_id',
        'total',
        'discount',
        'payment_method_id',
        'type',
        'status',
        'delivery_status',
        'shipping_fee',
        'is_maintenance',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'discount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'is_maintenance' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(SaleAdjustment::class)->latest();
    }
}
