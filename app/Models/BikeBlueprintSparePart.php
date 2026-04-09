<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprintSparePart extends Model
{
    use SoftDeletes;

    protected $fillable = ['bike_blueprint_id', 'spare_part_id'];

    public function bikeBlueprint(): BelongsTo
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    public function sparePart(): BelongsTo
    {
        return $this->belongsTo(SparePart::class);
    }
}
