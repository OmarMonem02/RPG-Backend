<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SparePartCategory extends Model
{
    use SoftDeletes;

    protected $table = 'spare_part_categories';

    protected $fillable = ['name'];

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class, 'spare_parts_category_id');
    }
}
