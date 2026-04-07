<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const CATEGORY_GOODS = 'goods';

    public const CATEGORY_BILLS = 'bills';

    public const CATEGORY_SUPPLIES = 'supplies';

    public const PAID_BY_CASH = 'cash';

    public const PAID_BY_BANK = 'bank';

    public const RECURRING_WEEKLY = 'weekly';

    public const RECURRING_MONTHLY = 'monthly';

    public const RECURRING_YEARLY = 'yearly';

    public const RECURRING_NONE = 'none';

    protected $fillable = [
        'category',
        'amount',
        'description',
        'expense_date',
        'paid_by',
        'attachment',
        'is_recurring',
        'recurring_type',
        'generated_from_id',
        'source_period_date',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_recurring' => 'boolean',
            'expense_date' => 'date',
            'source_period_date' => 'date',
        ];
    }
}
