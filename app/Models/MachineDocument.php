<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineDocument extends Model
{
    public const TYPE_INVOICE = 'invoice';

    public const TYPE_CONTRACT = 'contract';

    public const TYPES = [
        self::TYPE_INVOICE,
        self::TYPE_CONTRACT,
    ];

    protected $fillable = [
        'machine_id',
        'type',
        'url',
        'public_id',
        'filename',
        'mime_type',
        'uploaded_at',
    ];

    protected $casts = [
        'uploaded_at' => 'datetime',
    ];

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class);
    }
}
