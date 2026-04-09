<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenanceServiceSector extends Model
{
    use SoftDeletes;

    protected $fillable = ['name'];

    public function maintenanceServices(): HasMany
    {
        return $this->hasMany(MaintenanceService::class);
    }
}
