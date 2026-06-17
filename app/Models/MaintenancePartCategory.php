<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MaintenancePartCategory extends Model
{
    use LogsHistory, SoftDeletes;

    protected $table = 'maintenance_part_categories';

    protected $fillable = ['name'];

    public function maintenanceParts(): HasMany
    {
        return $this->hasMany(MaintenancePart::class, 'maintenance_parts_category_id');
    }
}
