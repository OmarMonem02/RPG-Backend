<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprintSparePart extends Model
{
    use SoftDeletes;

    protected $table = 'bike_blueprint_spare_parts';

    protected $fillable = [
        'bike_blueprint_id',
        'spare_part_id',
    ];

    // Relationships
    public function bikeBlueprint()
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    public function sparePart()
    {
        return $this->belongsTo(SparePart::class);
    }
}
