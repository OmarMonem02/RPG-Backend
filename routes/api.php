<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BikeBlueprintSparePartController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::middleware('role:admin,staff')->group(function () {
        Route::get('/sales', [SaleController::class, 'index']);
        Route::post('/sales', [SaleController::class, 'store']);
        Route::get('/sales/{sale}', [SaleController::class, 'show']);
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy']);
    });

    Route::middleware('role:admin,staff,Technician')->group(function () {
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus']);
    });

    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
        Route::apiResource('sellers', SellerController::class);

        // Nested resource for bike blueprint spare parts (must be before generic routes)
        Route::apiResource('bike_blueprints.spare_parts', BikeBlueprintSparePartController::class)
            ->only(['index', 'store', 'destroy']);

        // Generic entity routes (excludes bike_blueprint_spare_parts because it's handled above)
        $entities = [
            'customers',
            'products',
            'spare_parts',
            'product_categories',
            'spare_part_categories',
            'maintenance_service_sectors',
            'brands',
            'maintenance_services',
            'bike_for_sale',
            'customer_bikes',
            'bike_blueprints',
            'customer_sale',
            'sale_items',
            'payment_methods',
            'deliveries',
            'ticket_tasks',
            'ticket_items',
        ];

        foreach ($entities as $entity) {
            Route::get("/{$entity}", [EntityController::class, 'index'])->defaults('entity', $entity);
            Route::post("/{$entity}", [EntityController::class, 'store'])->defaults('entity', $entity);
            Route::get("/{$entity}/{id}", [EntityController::class, 'show'])->defaults('entity', $entity);
            Route::put("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity);
            Route::patch("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity);
            Route::delete("/{$entity}/{id}", [EntityController::class, 'destroy'])->defaults('entity', $entity);
        }

        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
    });
});
