<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Machine extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RETIRED = 'retired';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_RETIRED,
    ];

    public const CATEGORY_MACHINE = 'machine';

    public const CATEGORY_EQUIPMENT = 'equipment';

    public const CATEGORY_VEHICLE = 'vehicle';

    public const CATEGORIES = [
        self::CATEGORY_MACHINE,
        self::CATEGORY_EQUIPMENT,
        self::CATEGORY_VEHICLE,
    ];

    protected $fillable = [
        'name',
        'category',
        'serial_number',
        'location',
        'purchase_date',
        'purchase_cost',
        'status',
        'notes',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public function documents(): HasMany
    {
        return $this->hasMany(MachineDocument::class);
    }
}
