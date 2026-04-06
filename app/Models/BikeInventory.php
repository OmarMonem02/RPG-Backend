<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BikeInventory extends Model
{
    use HasFactory;

    protected $table = 'bikes_inventory';

    public const TYPE_OWNED = 'owned';
    public const TYPE_CONSIGNMENT = 'consignment';

    protected $fillable = [
        'type',
        'brand',
        'model',
        'year',
        'cost_price',
        'selling_price',
        'owner_name',
        'owner_phone',
    ];

    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'cost_price' => 'decimal:2',
            'selling_price' => 'decimal:2',
        ];
    }
}
