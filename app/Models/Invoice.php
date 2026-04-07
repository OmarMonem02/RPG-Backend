<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    public const TYPE_SALE = 'sale';

    public const TYPE_TICKET = 'ticket';

    public const STATUS_PAID = 'paid';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_UNPAID = 'unpaid';

    protected $fillable = [
        'invoice_number',
        'type',
        'reference_id',
        'total',
        'discount',
        'final_total',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'discount' => 'decimal:2',
            'final_total' => 'decimal:2',
        ];
    }
}
