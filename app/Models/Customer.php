<?php

namespace App\Models;

use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes, LogsHistory;

    protected $fillable = ['name', 'phone', 'address', 'how_did_you_know_us', 'notes'];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function customerBikes(): HasMany
    {
        return $this->hasMany(CustomerBike::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
