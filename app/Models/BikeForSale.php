<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeForSale extends Model
{
    use SoftDeletes;

    protected $table = 'bike_for_sale';

    protected $fillable = [
        'bike_blueprint_id',
        'currency_pricing',
        'cost_price',
        'sale_price',
        'status',
        'max_discount_type',
        'max_discount_value',
        'vin',
        'mileage',
        'notes',
    ];

    public function bikeBlueprint(): BelongsTo
    {
        return $this->belongsTo(BikeBlueprint::class);
    }
}
