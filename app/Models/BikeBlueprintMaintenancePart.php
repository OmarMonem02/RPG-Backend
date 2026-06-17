<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class BikeBlueprintMaintenancePart extends Model
{
    use SoftDeletes;

    protected $table = 'bike_blueprint_maintenance_parts';

    protected $fillable = [
        'bike_blueprint_id',
        'maintenance_part_id',
    ];

    public function bikeBlueprint()
    {
        return $this->belongsTo(BikeBlueprint::class);
    }

    public function maintenancePart()
    {
        return $this->belongsTo(MaintenancePart::class);
    }
}
