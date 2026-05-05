<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerBike extends Model
{
    use LogsHistory, SoftDeletes;

    protected $fillable = [
        'customer_id',
        'bike_blueprint_id',
        'image',
        'image_public_id',
        'vin',
        'mileage',
        'notes',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function bikeBlueprint(): BelongsTo
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
