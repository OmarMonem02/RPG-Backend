<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'task_id',
        'ticket_id',
        'spare_part_id',
        'maintenance_service_id',
        'price_snapshot',
        'discount',
        'qty',
        'subtotal',
    ];

    public function task(): BelongsTo
    {
        return $this->belongsTo(TicketTask::class, 'task_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }

    public function maintenanceService(): BelongsTo
    {
        return $this->belongsTo(MaintenanceService::class);
    }
}
