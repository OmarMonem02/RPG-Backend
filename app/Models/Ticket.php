<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'customer_bike_id',
        'status',
        'notes',
        'customer_notes',
        'total',
        'payment_method',
        'amount_paid',
        'closed_at',
        'public_token',
        'tracking_link_sent_at',
        'tracking_link_send_count',
    ];

    protected function casts(): array
    {
        return [
            'amount_paid' => 'decimal:2',
            'total' => 'decimal:2',
            'closed_at' => 'datetime',
            'tracking_link_sent_at' => 'datetime',
            'tracking_link_send_count' => 'integer',
        ];
    }

    public function isClosedAndFullyPaid(): bool
    {
        return $this->status === 'closed'
            && (float) $this->amount_paid >= (float) $this->total;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerBike(): BelongsTo
    {
        return $this->belongsTo(CustomerBike::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(TicketTask::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class);
    }

    /**
     * @return list<string>
     */
    public static function detailRelations(): array
    {
        return [
            'tasks.items.sparePart',
            'tasks.items.maintenanceService',
            'items.sparePart',
            'items.maintenanceService',
            'customer',
            'customerBike.bikeBlueprint.brand',
            'user',
        ];
    }
}
