<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use SoftDeletes;

    public const STATUS_PAID = 'paid';
    public const STATUS_UNPAID = 'unpaid';

    public const CATEGORY_RENT = 'rent';
    public const CATEGORY_SALARIES = 'salaries';
    public const CATEGORY_UTILITIES = 'utilities';
    public const CATEGORY_MARKETING = 'marketing';
    public const CATEGORY_TRANSPORT = 'transport';
    public const CATEGORY_MAINTENANCE = 'maintenance';
    public const CATEGORY_OTHER = 'other';

    public const CATEGORIES = [
        self::CATEGORY_RENT,
        self::CATEGORY_SALARIES,
        self::CATEGORY_UTILITIES,
        self::CATEGORY_MARKETING,
        self::CATEGORY_TRANSPORT,
        self::CATEGORY_MAINTENANCE,
        self::CATEGORY_OTHER,
    ];

    protected $fillable = [
        'title',
        'category',
        'amount',
        'currency',
        'payment_status',
        'incurred_on',
        'due_date',
        'paid_at',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'incurred_on' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];
}
