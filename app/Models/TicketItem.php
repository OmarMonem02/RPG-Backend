<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketItem extends Model
{
    use HasFactory;

    public const ITEM_TYPE_PRODUCT = 'product';
    public const ITEM_TYPE_SERVICE = 'service';

    public const PRICE_SOURCE_CURRENT = 'current';
    public const PRICE_SOURCE_OLD = 'old';

    protected $fillable = [
        'ticket_id',
        'task_id',
        'item_type',
        'item_id',
        'price_snapshot',
        'price_source',
        'qty',
    ];

    protected function casts(): array
    {
        return [
            'price_snapshot' => 'decimal:2',
            'qty' => 'integer',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TicketTask::class, 'task_id');
    }
}
