<?php

namespace App\Models;

use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketItem extends Model
{
    use SoftDeletes, LogsHistory;

    protected $appends = ['item_name'];

    protected $fillable = [
        'task_id',
        'ticket_id',
        'spare_part_id',
        'maintenance_part_id',
        'maintenance_service_id',
        'product_id',
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

    public function maintenancePart(): BelongsTo
    {
        return $this->belongsTo(MaintenancePart::class);
    }

    public function maintenanceService(): BelongsTo
    {
        return $this->belongsTo(MaintenanceService::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getItemNameAttribute(): string
    {
        if ($this->maintenance_part_id !== null) {
            return $this->maintenancePart?->name ?? 'Maintenance Part';
        }

        if ($this->spare_part_id !== null) {
            return $this->sparePart?->name ?? 'Spare Part';
        }

        if ($this->product_id !== null) {
            return $this->product?->name ?? 'Product';
        }

        return $this->maintenanceService?->name ?? 'Service';
    }
}
