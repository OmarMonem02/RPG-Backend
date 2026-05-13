<?php

namespace App\Models;

use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Seller extends Model
{
    use LogsHistory, SoftDeletes;

    protected $fillable = ['name', 'phone', 'commission_rate'];

    protected $casts = [
        'commission_rate' => 'decimal:2',
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function scopeSearch($query, ?string $search)
    {
        $term = trim((string) $search);

        if ($term === '') {
            return $query;
        }

        return $query->where(function ($query) use ($term) {
            $query
                ->where('name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%");

            if (ctype_digit($term)) {
                $query->orWhere('id', (int) $term);
            }
        });
    }
}
