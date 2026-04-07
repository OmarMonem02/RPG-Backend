<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PENDING = 'pending';

    public const TYPE_GARAGE = 'garage';

    public const TYPE_DELIVERY = 'delivery';

    public const TYPE_ONLINE = 'online';

    protected $fillable = [
        'customer_id',
        'seller_id',
        'total',
        'discount',
        'status',
        'type',
    ];

    protected $appends = [
        'final_amount',
        'paid_amount',
        'remaining_amount',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function getFinalAmountAttribute(): float
    {
        return round((float) $this->total - (float) $this->discount, 2);
    }

    public function getPaidAmountAttribute(): float
    {
        if ($this->relationLoaded('payments')) {
            return round((float) $this->payments
                ->where('status', Payment::STATUS_COMPLETED)
                ->sum('amount'), 2);
        }

        return round((float) $this->payments()
            ->where('status', Payment::STATUS_COMPLETED)
            ->sum('amount'), 2);
    }

    public function getRemainingAmountAttribute(): float
    {
        return max(round($this->final_amount - $this->paid_amount, 2), 0);
    }
}
