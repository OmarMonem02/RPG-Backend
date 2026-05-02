<?php
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BikeBlueprintController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('login');

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/sales/catalog-items', [SaleController::class, 'catalog'])->middleware('permission:sales,read');
    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales,read');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales,read');
    Route::get('/sales/{sale}/adjustments', [SaleController::class, 'adjustments'])->middleware('permission:sales,read');
    Route::post('/sales', [SaleController::class, 'store'])->middleware('permission:sales,create');
    Route::patch('/sales/{sale}', [SaleController::class, 'update'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/items', [SaleController::class, 'addItem'])->middleware('permission:sales,update');
    Route::patch('/sales/{sale}/items/{saleItem}', [SaleController::class, 'updateItem'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/returns', [SaleController::class, 'returns'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/exchanges', [SaleController::class, 'exchanges'])->middleware('permission:sales,update');
    Route::delete('/sales/{sale}/items/{saleItem}', [SaleController::class, 'removeItem'])->middleware('permission:sales,delete');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->middleware('permission:sales,delete');

    Route::get('/tickets', [TicketController::class, 'index'])->middleware('permission:maintenance,read');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->middleware('permission:maintenance,read');
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('permission:maintenance,create');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/tasks', [TicketController::class, 'addTask'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/tasks/{task}', [TicketController::class, 'updateTask'])->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}/tasks/{task}', [TicketController::class, 'deleteTask'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/tasks/{task}/items', [TicketController::class, 'addItem'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/tasks/{task}/items/{item}', [TicketController::class, 'updateItem'])->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}/tasks/{task}/items/{item}', [TicketController::class, 'deleteItem'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/end', [TicketController::class, 'end'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->middleware('permission:maintenance,delete');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users,read');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users,create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users,read');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware('permission:users,update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users,delete');
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->middleware('role:admin');

    Route::get('/sellers', [SellerController::class, 'index'])->middleware('permission:sellers,read');
    Route::post('/sellers', [SellerController::class, 'store'])->middleware('permission:sellers,create');
    Route::get('/sellers/{seller}', [SellerController::class, 'show'])->middleware('permission:sellers,read');
    Route::match(['put', 'patch'], '/sellers/{seller}', [SellerController::class, 'update'])->middleware('permission:sellers,update');
    Route::delete('/sellers/{seller}', [SellerController::class, 'destroy'])->middleware('permission:sellers,delete');

    Route::get('/spare_parts/low-stock', [SparePartController::class, 'lowStock'])->middleware('permission:spare-parts,read');
    Route::get('/spare_parts', [SparePartController::class, 'index'])->middleware('permission:spare-parts,read');
    Route::post('/spare_parts', [SparePartController::class, 'store'])->middleware('permission:spare-parts,create');
    Route::patch('/spare_parts/{spare_part}/stock', [SparePartController::class, 'updateStock'])->middleware('permission:spare-parts,update');
    Route::post('/spare_parts/bulk/create', [SparePartController::class, 'bulkCreate'])->middleware('permission:spare-parts,create');
    Route::patch('/spare_parts/bulk/update', [SparePartController::class, 'bulkUpdate'])->middleware('permission:spare-parts,update');
    Route::delete('/spare_parts/bulk/delete', [SparePartController::class, 'bulkDelete'])->middleware('permission:spare-parts,delete');
    Route::get('/spare_parts/{spare_part}', [SparePartController::class, 'show'])->middleware('permission:spare-parts,read');
    Route::match(['put', 'patch'], '/spare_parts/{spare_part}', [SparePartController::class, 'update'])->middleware('permission:spare-parts,update');
    Route::delete('/spare_parts/{spare_part}', [SparePartController::class, 'destroy'])->middleware('permission:spare-parts,delete');

    Route::get('/bike_blueprints', [BikeBlueprintController::class, 'index'])->middleware('permission:bike-blueprints,read');
    Route::post('/bike_blueprints', [BikeBlueprintController::class, 'store'])->middleware('permission:bike-blueprints,create');
    Route::post('/bike_blueprints/bulk/assign-spare-parts', [BikeBlueprintController::class, 'bulkAssignSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::get('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'getLinkedSpareParts'])->middleware('permission:bike-blueprints,read');
    Route::post('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'assignSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::put('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'replaceSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::delete('/bike_blueprints/{bike_blueprint}/spare_parts/{spare_part}', [BikeBlueprintController::class, 'removeSparePart'])->middleware('permission:bike-blueprints,delete');
    Route::get('/bike_blueprints/{bike_blueprint}/bikes', [BikeBlueprintController::class, 'getLinkedBikes'])->middleware('permission:bike-blueprints,read');
    Route::get('/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'show'])->middleware('permission:bike-blueprints,read');
    Route::match(['put', 'patch'], '/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'update'])->middleware('permission:bike-blueprints,update');
    Route::delete('/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'destroy'])->middleware('permission:bike-blueprints,delete');

    $permissionEntities = [
        'brands' => 'brands',
        'products' => 'products',
        'product_categories' => 'product-categories',
        'spare_part_categories' => 'spare-part-categories',
        'maintenance_services' => 'maintenance-services',
        'bike_for_sale' => 'bikes',
        'payment_methods' => 'payment-methods',
    ];

    foreach ($permissionEntities as $entity => $page) {
        Route::get("/{$entity}", [EntityController::class, 'index'])->defaults('entity', $entity)->middleware("permission:{$page},read");
        Route::post("/{$entity}", [EntityController::class, 'store'])->defaults('entity', $entity)->middleware("permission:{$page},create");
        Route::get("/{$entity}/{id}", [EntityController::class, 'show'])->defaults('entity', $entity)->middleware("permission:{$page},read");
        Route::put("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity)->middleware("permission:{$page},update");
        Route::patch("/{$entity}/{id}", [EntityController::class, 'update'])->defaults('entity', $entity)->middleware("permission:{$page},update");
        Route::delete("/{$entity}/{id}", [EntityController::class, 'destroy'])->defaults('entity', $entity)->middleware("permission:{$page},delete");
    }

    Route::get('/import-export/entities', [ImportExportController::class, 'entities'])->middleware('permission:import-export,read');
    Route::prefix('import-export/{entity}')->group(function () {
        Route::get('/export', [ImportExportController::class, 'export'])->middleware('permission:import-export,export');
        Route::get('/template', [ImportExportController::class, 'template'])->middleware('permission:import-export,export');
        Route::post('/import', [ImportExportController::class, 'import'])->middleware('permission:import-export,import');
        Route::post('/parse', [ImportExportController::class, 'parse'])->middleware('permission:import-export,import');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/reporting/profit-loss', [ReportingController::class, 'profitLoss']);
        Route::get('/reporting/balance-sheet', [ReportingController::class, 'balanceSheet']);
        Route::get('/reporting/annual-summary', [ReportingController::class, 'annualSummary']);
        Route::get('/reporting/expenses', [ReportingController::class, 'expenses']);

        Route::get('/expenses', [ExpenseController::class, 'index']);
        Route::post('/expenses', [ExpenseController::class, 'store']);
        Route::patch('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::put('/expenses/{expense}', [ExpenseController::class, 'update']);
        Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy']);

        $entities = [
            'customers',
            'maintenance_service_sectors',
            'customer_bikes',
            'customer_sale',
            'sale_items',
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
        Route::get('/history', [HistoryController::class, 'index']);
    });
});
