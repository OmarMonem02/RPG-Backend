<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExchangeRate extends Model
{
    use HasFactory;

    public const USD = 'USD';

    protected $fillable = [
        'currency',
        'value',
        'rate',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'rate' => 'decimal:4',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
