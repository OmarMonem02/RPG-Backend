<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CustomerSale extends Model
{
    use SoftDeletes;

    protected $table = 'customer_sale';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $fillable = ['customer_id', 'sale_id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
