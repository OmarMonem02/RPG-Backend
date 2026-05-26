<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketMessage extends Model
{
    public const SENDER_CUSTOMER = 'customer';

    public const SENDER_STAFF = 'staff';

    protected $fillable = [
        'ticket_id',
        'sender_type',
        'user_id',
        'body',
        'image_url',
        'image_public_id',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
