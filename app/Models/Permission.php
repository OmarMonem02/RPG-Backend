<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    use HasFactory;

    public const VIEW_SALES = 'view_sales';
    public const CREATE_SALE = 'create_sale';
    public const EDIT_SALE = 'edit_sale';
    public const VIEW_INVENTORY = 'view_inventory';
    public const EDIT_INVENTORY = 'edit_inventory';
    public const VIEW_REPORTS = 'view_reports';
    public const MANAGE_USERS = 'manage_users';
    public const VIEW_TICKETS = 'view_tickets';
    public const UPDATE_TASKS = 'update_tasks';

    protected $fillable = [
        'name',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_permissions');
    }
}
