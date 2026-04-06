<?php

namespace App\Providers;

use App\Models\Expense;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Ticket;
use App\Models\User;
use App\Observers\ExpenseObserver;
use App\Observers\ProductObserver;
use App\Observers\SaleObserver;
use App\Observers\TicketObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Product::observe(ProductObserver::class);
        Sale::observe(SaleObserver::class);
        Ticket::observe(TicketObserver::class);
        Expense::observe(ExpenseObserver::class);
        User::observe(UserObserver::class);
    }
}
