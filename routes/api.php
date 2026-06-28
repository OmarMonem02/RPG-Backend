<?php

use App\Http\Controllers\Api\ApprovalRequestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\BikeBlueprintController;
use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\EntityController;
use App\Http\Controllers\Api\ExportColumnController;
use App\Http\Controllers\Api\ExpenseController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\ImportExportController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\PricingAlarmsController;
use App\Http\Controllers\Api\ProductBulkController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\SaleController;
use App\Http\Controllers\Api\SellerController;
use App\Http\Controllers\Api\SettingController;
use App\Http\Controllers\Api\MaintenancePartController;
use App\Http\Controllers\Api\SparePartController;
use App\Http\Controllers\Api\StocktakeController;
use App\Http\Controllers\Api\PublicTicketMessageController;
use App\Http\Controllers\Api\PublicTicketTrackingController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketMessageController;
use App\Http\Controllers\Api\TicketTrackingController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login'])->name('login');

Route::prefix('public/tickets')->group(function () {
    Route::get('{token}/meta', [PublicTicketTrackingController::class, 'meta'])
        ->middleware('throttle:30,1');
    Route::post('{token}/verify', [PublicTicketTrackingController::class, 'verify'])
        ->middleware('throttle:ticket-verify');
    Route::get('{token}/messages', [PublicTicketMessageController::class, 'index'])
        ->middleware('throttle:60,1');
    Route::post('{token}/messages', [PublicTicketMessageController::class, 'store'])
        ->middleware('throttle:10,1');
    Route::get('{token}', [PublicTicketTrackingController::class, 'show'])
        ->middleware('throttle:60,1');
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/auth/verify-admin-password', [AuthController::class, 'verifyAdminPassword']);
    Route::get('/permissions/meta', [PermissionController::class, 'meta']);
    Route::post('/upload-image', [ImageController::class, 'upload']);
    Route::delete('/delete-image', [ImageController::class, 'destroy']);
    Route::post('/upload-document', [DocumentController::class, 'upload'])->middleware('permission:machines,create');
    Route::delete('/delete-document', [DocumentController::class, 'destroy'])->middleware('permission:machines,update');

    Route::get('/machines', [MachineController::class, 'index'])->middleware('permission:machines,read');
    Route::post('/machines', [MachineController::class, 'store'])->middleware('permission:machines,create');
    Route::get('/machines/{machine}', [MachineController::class, 'show'])->middleware('permission:machines,read');
    Route::put('/machines/{machine}', [MachineController::class, 'update'])->middleware('permission:machines,update');
    Route::patch('/machines/{machine}', [MachineController::class, 'update'])->middleware('permission:machines,update');
    Route::delete('/machines/{machine}', [MachineController::class, 'destroy'])->middleware('permission:machines,delete');

    Route::get('/sales/catalog-items', [SaleController::class, 'catalog'])->middleware('permission:sales,read');
    Route::get('/sales/export', [SaleController::class, 'export'])->middleware('permission:sales,export');
    Route::get('/sales', [SaleController::class, 'index'])->middleware('permission:sales,read');
    Route::get('/sales/{sale}', [SaleController::class, 'show'])->middleware('permission:sales,read');
    Route::get('/sales/{sale}/adjustments', [SaleController::class, 'adjustments'])->middleware('permission:sales,read');
    Route::get('/sales/{sale}/history', [SaleController::class, 'history'])->middleware('permission:sales,read');
    Route::post('/sales', [SaleController::class, 'store'])->middleware('permission:sales,create');
    Route::patch('/sales/{sale}', [SaleController::class, 'update'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/items', [SaleController::class, 'addItem'])->middleware('permission:sales,update');
    Route::patch('/sales/{sale}/items/{saleItem}', [SaleController::class, 'updateItem'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/returns', [SaleController::class, 'returns'])->middleware('permission:sales,update');
    Route::post('/sales/{sale}/exchanges', [SaleController::class, 'exchanges'])->middleware('permission:sales,update');
    Route::delete('/sales/{sale}/items/{saleItem}', [SaleController::class, 'removeItem'])->middleware('permission:sales,delete');
    Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->middleware('permission:sales,delete');

    Route::get('/approval-requests/pending-count', [ApprovalRequestController::class, 'pendingCount']);
    Route::get('/approval-requests', [ApprovalRequestController::class, 'index']);
    Route::post('/approval-requests', [ApprovalRequestController::class, 'store'])
        ->middleware('any_permission:sales,create,maintenance,update');
    Route::get('/approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'show']);
    Route::post('/approval-requests/{approvalRequest}/approve', [ApprovalRequestController::class, 'approve']);
    Route::post('/approval-requests/{approvalRequest}/reject', [ApprovalRequestController::class, 'reject']);
    Route::delete('/approval-requests/{approvalRequest}', [ApprovalRequestController::class, 'destroy']);

    Route::get('/tickets/export', [TicketController::class, 'export'])->middleware('permission:maintenance,read');
    Route::get('/tickets', [TicketController::class, 'index'])->middleware('permission:maintenance,read');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->middleware('permission:maintenance,read');
    Route::post('/tickets', [TicketController::class, 'store'])->middleware('permission:maintenance,create');
    Route::patch('/tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/notes', [TicketController::class, 'updateNotes'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/discount', [TicketController::class, 'updateDiscount'])->middleware('permission:maintenance,update');
    Route::get('/tickets/{ticket}/messages', [TicketMessageController::class, 'index'])->middleware('permission:maintenance,read');
    Route::post('/tickets/{ticket}/messages', [TicketMessageController::class, 'store'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/tasks', [TicketController::class, 'addTask'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/tasks/{task}', [TicketController::class, 'updateTask'])->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}/tasks/{task}', [TicketController::class, 'deleteTask'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/tasks/{task}/items', [TicketController::class, 'addItem'])->middleware('permission:maintenance,update');
    Route::patch('/tickets/{ticket}/tasks/{task}/items/{item}', [TicketController::class, 'updateItem'])->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}/tasks/{task}/items/{item}', [TicketController::class, 'deleteItem'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/end', [TicketController::class, 'end'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/close', [TicketController::class, 'close'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/payment', [TicketController::class, 'recordPayment'])->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/ensure-tracking-link', [TicketTrackingController::class, 'ensureTrackingLink'])
        ->middleware('permission:maintenance,update');
    Route::post('/tickets/{ticket}/send-tracking-link', [TicketTrackingController::class, 'sendTrackingLink'])
        ->middleware(['permission:maintenance,update', 'throttle:3,1']);
    Route::post('/tickets/{ticket}/regenerate-tracking-token', [TicketTrackingController::class, 'regenerateTrackingToken'])
        ->middleware('permission:maintenance,update');
    Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy'])->middleware('permission:maintenance,delete');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('any_permission:sales,read,maintenance,read');
    Route::get('/customers/{customer}/workspace', [CustomerController::class, 'workspace'])->middleware('any_permission:sales,read,maintenance,read');
    Route::get('/customers/{customer}/addresses', [CustomerController::class, 'indexAddresses'])
        ->middleware('any_permission:sales,read,maintenance,read');
    Route::post('/customers/{customer}/addresses', [CustomerController::class, 'storeAddress'])
        ->middleware('any_permission:maintenance,create,sales,create');
    Route::post('/customers/{customer}/bikes', [CustomerController::class, 'storeBike'])
        ->middleware('any_permission:maintenance,create,sales,create');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users,read');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users,create');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users,read');
    Route::match(['put', 'patch'], '/users/{user}', [UserController::class, 'update'])->middleware('permission:users,update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users,delete');
    Route::put('/users/{user}/permissions', [UserController::class, 'updatePermissions'])->middleware('permission:users,update');

    Route::get('/sellers', [SellerController::class, 'index'])->middleware('permission:sellers,read');
    Route::post('/sellers', [SellerController::class, 'store'])->middleware('permission:sellers,create');
    Route::get('/sellers/{seller}/monthly-history', [SellerController::class, 'monthlyHistory'])->middleware('permission:sellers,read');
    Route::get('/sellers/{seller}', [SellerController::class, 'show'])->middleware('permission:sellers,read');
    Route::match(['put', 'patch'], '/sellers/{seller}', [SellerController::class, 'update'])->middleware('permission:sellers,update');
    Route::delete('/sellers/{seller}', [SellerController::class, 'destroy'])->middleware('permission:sellers,delete');

    Route::get('/spare_parts/low-stock', [SparePartController::class, 'lowStock'])->middleware('permission:spare-parts,read');
    Route::get('/spare_parts', [SparePartController::class, 'index'])->middleware('permission:spare-parts,read');
    Route::post('/spare_parts', [SparePartController::class, 'store'])->middleware('permission:spare-parts,create');
    Route::patch('/spare_parts/{spare_part}/stock', [SparePartController::class, 'updateStock'])->middleware('permission:spare-parts,update');
    Route::post('/spare_parts/bulk/preview', [SparePartController::class, 'bulkPreview'])->middleware('permission:spare-parts,update');
    Route::patch('/spare_parts/bulk/apply', [SparePartController::class, 'bulkApply'])->middleware('permission:spare-parts,update');
    Route::post('/spare_parts/bulk/create', [SparePartController::class, 'bulkCreate'])->middleware('permission:spare-parts,create');
    Route::patch('/spare_parts/bulk/update', [SparePartController::class, 'bulkUpdate'])->middleware('permission:spare-parts,update');
    Route::delete('/spare_parts/bulk/delete', [SparePartController::class, 'bulkDelete'])->middleware('permission:spare-parts,delete');
    Route::get('/spare_parts/{spare_part}', [SparePartController::class, 'show'])->middleware('permission:spare-parts,read');
    Route::match(['put', 'patch'], '/spare_parts/{spare_part}', [SparePartController::class, 'update'])->middleware('permission:spare-parts,update');
    Route::delete('/spare_parts/{spare_part}', [SparePartController::class, 'destroy'])->middleware('permission:spare-parts,delete');

    Route::get('/maintenance_parts/low-stock', [MaintenancePartController::class, 'lowStock'])->middleware('permission:maintenance-parts,read');
    Route::get('/maintenance_parts', [MaintenancePartController::class, 'index'])->middleware('permission:maintenance-parts,read');
    Route::post('/maintenance_parts', [MaintenancePartController::class, 'store'])->middleware('permission:maintenance-parts,create');
    Route::patch('/maintenance_parts/{maintenance_part}/stock', [MaintenancePartController::class, 'updateStock'])->middleware('permission:maintenance-parts,update');
    Route::post('/maintenance_parts/bulk/preview', [MaintenancePartController::class, 'bulkPreview'])->middleware('permission:maintenance-parts,update');
    Route::patch('/maintenance_parts/bulk/apply', [MaintenancePartController::class, 'bulkApply'])->middleware('permission:maintenance-parts,update');
    Route::post('/maintenance_parts/bulk/create', [MaintenancePartController::class, 'bulkCreate'])->middleware('permission:maintenance-parts,create');
    Route::patch('/maintenance_parts/bulk/update', [MaintenancePartController::class, 'bulkUpdate'])->middleware('permission:maintenance-parts,update');
    Route::delete('/maintenance_parts/bulk/delete', [MaintenancePartController::class, 'bulkDelete'])->middleware('permission:maintenance-parts,delete');
    Route::get('/maintenance_parts/{maintenance_part}', [MaintenancePartController::class, 'show'])->middleware('permission:maintenance-parts,read');
    Route::match(['put', 'patch'], '/maintenance_parts/{maintenance_part}', [MaintenancePartController::class, 'update'])->middleware('permission:maintenance-parts,update');
    Route::delete('/maintenance_parts/{maintenance_part}', [MaintenancePartController::class, 'destroy'])->middleware('permission:maintenance-parts,delete');

    Route::get('/bike_blueprints', [BikeBlueprintController::class, 'index'])->middleware('permission:bike-blueprints,read');
    Route::post('/bike_blueprints', [BikeBlueprintController::class, 'store'])->middleware('permission:bike-blueprints,create');
    // Allow spare-part users to use compatibility cascading filters (brand -> model -> year)
    Route::get('/bike_blueprints/filter/models', [BikeBlueprintController::class, 'getModelsByBrand'])->middleware('any_permission:spare-parts,read,products,read,maintenance-parts,read');
    Route::get('/bike_blueprints/filter/years', [BikeBlueprintController::class, 'getYearsByBrandAndModel'])->middleware('any_permission:spare-parts,read,products,read,maintenance-parts,read');
    Route::post('/bike_blueprints/bulk/create-by-range', [BikeBlueprintController::class, 'bulkCreateByYearRange'])->middleware('permission:bike-blueprints,create');
    Route::post('/bike_blueprints/bulk/assign-spare-parts', [BikeBlueprintController::class, 'bulkAssignSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::post('/bike_blueprints/bulk/assign-maintenance-parts', [BikeBlueprintController::class, 'bulkAssignMaintenanceParts'])->middleware('permission:bike-blueprints,update');
    Route::get('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'getLinkedSpareParts'])->middleware('permission:bike-blueprints,read');
    Route::post('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'assignSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::put('/bike_blueprints/{bike_blueprint}/spare_parts', [BikeBlueprintController::class, 'replaceSpareParts'])->middleware('permission:bike-blueprints,update');
    Route::delete('/bike_blueprints/{bike_blueprint}/spare_parts/{spare_part}', [BikeBlueprintController::class, 'removeSparePart'])->middleware('permission:bike-blueprints,delete');
    Route::get('/bike_blueprints/{bike_blueprint}/maintenance_parts', [BikeBlueprintController::class, 'getLinkedMaintenanceParts'])->middleware('permission:bike-blueprints,read');
    Route::post('/bike_blueprints/{bike_blueprint}/maintenance_parts', [BikeBlueprintController::class, 'assignMaintenanceParts'])->middleware('permission:bike-blueprints,update');
    Route::put('/bike_blueprints/{bike_blueprint}/maintenance_parts', [BikeBlueprintController::class, 'replaceMaintenanceParts'])->middleware('permission:bike-blueprints,update');
    Route::delete('/bike_blueprints/{bike_blueprint}/maintenance_parts/{maintenance_part}', [BikeBlueprintController::class, 'removeMaintenancePart'])->middleware('permission:bike-blueprints,delete');
    Route::get('/bike_blueprints/{bike_blueprint}/bikes', [BikeBlueprintController::class, 'getLinkedBikes'])->middleware('permission:bike-blueprints,read');
    Route::get('/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'show'])->middleware('permission:bike-blueprints,read');
    Route::match(['put', 'patch'], '/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'update'])->middleware('permission:bike-blueprints,update');
    Route::delete('/bike_blueprints/{bike_blueprint}', [BikeBlueprintController::class, 'destroy'])->middleware('permission:bike-blueprints,delete');

    Route::post('/products/bulk/preview', [ProductBulkController::class, 'preview'])->middleware('permission:products,update');
    Route::patch('/products/bulk/apply', [ProductBulkController::class, 'apply'])->middleware('permission:products,update');

    Route::post('/stocktake/discrepancy-export', [StocktakeController::class, 'discrepancyExport'])
        ->middleware('any_permission:products,read,spare-parts,read,maintenance-parts,read');

    $permissionEntities = [
        'brands' => 'brands',
        'products' => 'products',
        'product_categories' => 'product-categories',
        'spare_part_categories' => 'spare-part-categories',
        'maintenance_part_categories' => 'maintenance-part-categories',
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

    Route::get('/export-columns', [ExportColumnController::class, 'index'])->middleware('permission:import-export,read');
    Route::get('/import-export/entities', [ImportExportController::class, 'entities'])->middleware('permission:import-export,read');
    Route::prefix('import-export/{entity}')->group(function () {
        Route::get('/export', [ImportExportController::class, 'export'])->middleware('permission:import-export,export');
        Route::get('/template', [ImportExportController::class, 'template'])->middleware('permission:import-export,export');
        Route::post('/import', [ImportExportController::class, 'import'])->middleware('permission:import-export,import');
        Route::post('/parse', [ImportExportController::class, 'parse'])->middleware('permission:import-export,import');
    });

    Route::get('/reporting/overview/export', [ReportingController::class, 'exportOverview'])->middleware('permission:reporting,read');
    Route::get('/reporting/profit-loss/export', [ReportingController::class, 'exportProfitLoss'])->middleware('permission:reporting,read');
    Route::get('/reporting/balance-sheet/export', [ReportingController::class, 'exportBalanceSheet'])->middleware('permission:reporting,read');
    Route::get('/reporting/annual-summary/export', [ReportingController::class, 'exportAnnualSummary'])->middleware('permission:reporting,read');
    Route::get('/reporting/expenses/export', [ReportingController::class, 'exportExpenses'])->middleware('permission:reporting,read');
    Route::get('/reporting/profit-loss', [ReportingController::class, 'profitLoss'])->middleware('permission:reporting,read');
    Route::get('/reporting/balance-sheet', [ReportingController::class, 'balanceSheet'])->middleware('permission:reporting,read');
    Route::get('/reporting/annual-summary', [ReportingController::class, 'annualSummary'])->middleware('permission:reporting,read');
    Route::get('/reporting/expenses', [ReportingController::class, 'expenses'])->middleware('permission:reporting,read');

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:reporting,read');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:reporting,create');
    Route::patch('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:reporting,update');
    Route::put('/expenses/{expense}', [ExpenseController::class, 'update'])->middleware('permission:reporting,update');
    Route::delete('/expenses/{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:reporting,delete');

    Route::middleware('role:admin')->group(function () {

        $entities = [
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

        Route::post('/customers', [EntityController::class, 'store'])->defaults('entity', 'customers');
        Route::put('/customers/{id}', [EntityController::class, 'update'])->defaults('entity', 'customers');
        Route::patch('/customers/{id}', [EntityController::class, 'update'])->defaults('entity', 'customers');
        Route::delete('/customers/{id}', [EntityController::class, 'destroy'])->defaults('entity', 'customers');

        Route::get('/inventory/pricing-alarms', [PricingAlarmsController::class, 'index'])
            ->middleware('any_permission:spare-parts,read,products,read,bikes,read');
        Route::post('/inventory/pricing-alarms/preview', [PricingAlarmsController::class, 'preview'])
            ->middleware('any_permission:spare-parts,read,products,read,bikes,read');
        Route::post('/inventory/pricing-alarms/preview-rate-change', [PricingAlarmsController::class, 'previewRateChange'])
            ->middleware('role:admin');
        Route::post('/inventory/pricing-alarms/apply', [PricingAlarmsController::class, 'apply'])
            ->middleware('any_permission:spare-parts,update,products,update,bikes,update');

        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);
        Route::get('/history', [HistoryController::class, 'index']);
        Route::get('/history/export', [HistoryController::class, 'export']);

        Route::get('/backup/export', [BackupController::class, 'export']);
        Route::post('/backup/preview', [BackupController::class, 'preview']);
        Route::post('/backup/import', [BackupController::class, 'import']);
    });
});
