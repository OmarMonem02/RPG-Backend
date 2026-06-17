<?php

namespace App\Models;

use App\Support\CaseInsensitiveLike;
use App\Traits\LogsHistory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Brand extends Model
{
    use SoftDeletes, LogsHistory;

    public const VALID_TYPES = ['spare_parts', 'products', 'bikes', 'maintenance_parts'];

    protected $fillable = ['name', 'types'];

    protected $casts = [
        'types' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function spareParts(): HasMany
    {
        return $this->hasMany(SparePart::class);
    }

    public function bikeBlueprints(): HasMany
    {
        return $this->hasMany(BikeBlueprint::class);
    }

    public function hasType(string $type): bool
    {
        return in_array($type, $this->types ?? [], true);
    }

    /**
     * @param  array<int, string>|null  $existing
     * @param  array<int, string>  $additional
     * @return array<int, string>
     */
    public static function mergeTypes(?array $existing, array $additional): array
    {
        return array_values(array_unique(array_merge($existing ?? [], $additional)));
    }

    /**
     * @return array<int, string>
     */
    public static function parseTypes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, fn ($type) => is_string($type) && $type !== ''));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (string $type) => trim($type),
            preg_split('/\s*,\s*/', $value) ?: []
        )));
    }

    // Scopes
    public function scopeSearch($query, ?string $search)
    {
        if (! $search) {
            return $query;
        }

        return CaseInsensitiveLike::where($query, 'name', $search);
    }

    public function scopeByType($query, ?string $type)
    {
        return $type ? $query->whereJsonContains('types', $type) : $query;
    }

    public function scopeByCreatedRange($query, ?string $from, ?string $to)
    {
        if ($from) {
            $query = $query->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $query = $query->whereDate('created_at', '<=', $to);
        }

        return $query;
    }
}
