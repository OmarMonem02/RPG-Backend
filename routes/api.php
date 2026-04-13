<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BikeBlueprintController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->name('login');

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

        // Spare Part Routes
        Route::apiResource('spare_parts', SparePartController::class);
        Route::get('/spare_parts/low-stock', [SparePartController::class, 'lowStock']);
        Route::patch('/spare_parts/{spare_part}/stock', [SparePartController::class, 'updateStock']);
        Route::post('/spare_parts/bulk/create', [SparePartController::class, 'bulkCreate']);
        Route::patch('/spare_parts/bulk/update', [SparePartController::class, 'bulkUpdate']);
        Route::delete('/spare_parts/bulk/delete', [SparePartController::class, 'bulkDelete']);

        Route::apiResource('bike_blueprints', BikeBlueprintController::class);
        Route::get('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'getLinkedSpareParts']);
        Route::post('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'assignSpareParts']);
        Route::put('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'replaceSpareParts']);
        Route::delete('/bike_blueprints/{bike_blueprint}/spare_parts/{spare_part}', [BikeBlueprintController::class, 'removeSparePart']);
        Route::get('/bike_blueprints/{bike_blueprint}/bikes', [BikeBlueprintController::class, 'getLinkedBikes']);
        Route::post('/bike_blueprints/bulk/assign-spare-parts', [BikeBlueprintController::class, 'bulkAssignSpareParts']);


        // Generic entity routes
        $entities = [
            'customers',
            'products',
            'product_categories',
            'spare_part_categories',
            'maintenance_services',
            'maintenance_service_sectors',
            'brands',
            'bike_for_sale',
            'customer_bikes',
            'customer_sale',
            'sale_items',
            'payment_methods',
            'deliveries',
            'ticket_tasks',
            'ticket_items',
            'settings',
        ];

        foreach ($entities as $entity) {
            Route::get("/{$entity}", [EntityController::class, 'index'])->defaults('entity', $entity);
            Route::post("/{$entity}", [EntityController::class, 'store'])->defaults('entity', $entity);
            Route::get("/{$entity}/{id}", [EntityController::class, 'show'])->defaults('entity', $entity);
            Route::put("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity);
            Route::patch("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity);
            Route::delete("/{$entity}/{id}", [EntityController::class, 'destroy'])->defaults('entity', $entity);
        }
        
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);

        // ── Import / Export Routes ─────────────────────────────────────────────
        // List all supported entities
        Route::get('/import-export/entities', [ImportExportController::class, 'entities']);

        // Per-entity endpoints  (entity: products | spare_parts | maintenance_services
        //                                | bikes | bike_blueprints | brands)
        Route::prefix('import-export/{entity}')->group(function () {
            // GET  /api/import-export/{entity}/export?format=xlsx|csv
            Route::get('/export',   [ImportExportController::class, 'export']);
            // GET  /api/import-export/{entity}/template?format=xlsx|csv
            Route::get('/template', [ImportExportController::class, 'template']);
            // POST /api/import-export/{entity}/import   (multipart: file)
            Route::post('/import',  [ImportExportController::class, 'import']);
            // POST /api/import-export/{entity}/parse   (multipart: file)
            Route::post('/parse',   [ImportExportController::class, 'parse']);
        });

        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);
    });
});
