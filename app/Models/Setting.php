<?php

namespace App\Models;

use App\Traits\LogsHistory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    use LogsHistory;
    /** @use HasFactory<\Database\Factories\SettingFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'key',
        'value',
    ];

}
