<?php

namespace App\Models;

use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentMethod extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = ['name'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
