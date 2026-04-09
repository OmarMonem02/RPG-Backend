<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'phone', 'commission_rate'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
