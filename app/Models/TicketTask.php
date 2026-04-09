<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketTask extends Model
{
    use SoftDeletes;

    protected $fillable = ['ticket_id', 'name', 'status', 'subtotal'];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TicketItem::class, 'task_id');
    }
}
