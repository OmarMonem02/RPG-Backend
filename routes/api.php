<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BikeController;
use App\Http\Controllers\BikeInventoryController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerBikeController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InventoryLogController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use App\Models\Permission;
use Illuminate\Support\Facades\Route;

Route::post('auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('auth/me', [AuthController::class, 'me']);
    Route::post('auth/logout', [AuthController::class, 'logout']);

    Route::prefix('users')->middleware('permission:'.Permission::MANAGE_USERS)->group(function (): void {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('{user}', [UserController::class, 'update']);
        Route::post('{user}/permissions', [UserController::class, 'assignPermissions']);
    });

    Route::prefix('sales')->group(function (): void {
        Route::get('/', [SaleController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::get('{sale}', [SaleController::class, 'show'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('/', [SaleController::class, 'store'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::post('calculate', [SaleController::class, 'calculate'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::post('{sale}/items', [SaleController::class, 'addItem'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::post('{sale}/payments', [SaleController::class, 'addPayment'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::get('{sale}/payments', [PaymentController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::get('{sale}/invoice', [SaleController::class, 'invoice'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('{sale}/complete', [SaleController::class, 'complete'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::get('{sale}/returns', [ReturnController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('{sale}/returns', [ReturnController::class, 'store'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::post('{sale}/return', [SaleController::class, 'returnSale'])->middleware('permission:'.Permission::EDIT_SALE);
    });

    Route::prefix('sellers')->middleware('permission:'.Permission::MANAGE_USERS)->group(function (): void {
        Route::get('/', [SellerController::class, 'index']);
        Route::post('/', [SellerController::class, 'store']);
        Route::get('{seller}', [SellerController::class, 'show']);
        Route::put('{seller}', [SellerController::class, 'update']);
        Route::delete('{seller}', [SellerController::class, 'destroy']);
        Route::patch('{seller}/status', [SellerController::class, 'updateStatus']);
        Route::get('{seller}/sales', [SellerController::class, 'sales']);
    });

    Route::put('payments/{payment}', [PaymentController::class, 'update'])->middleware('permission:'.Permission::EDIT_SALE);
    Route::delete('payments/{payment}', [PaymentController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_SALE);

    Route::prefix('tickets')->group(function (): void {
        Route::get('/', [TicketController::class, 'index'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::get('{ticket}', [TicketController::class, 'show'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::post('/', [TicketController::class, 'store'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::post('{ticket}/start', [TicketController::class, 'start'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::post('{ticket}/complete', [TicketController::class, 'complete'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::post('{ticket}/reopen', [TicketController::class, 'reopen'])->middleware('permission:'.Permission::VIEW_TICKETS);
        Route::post('{ticket}/notes', [TicketController::class, 'addNote'])->middleware('permission:'.Permission::VIEW_TICKETS);

        Route::post('{ticket}/tasks', [TaskController::class, 'store'])->middleware('permission:'.Permission::UPDATE_TASKS);
        Route::put('{ticket}/tasks/{task}', [TaskController::class, 'update'])->middleware('permission:'.Permission::UPDATE_TASKS);
        Route::delete('{ticket}/tasks/{task}', [TaskController::class, 'destroy'])->middleware('permission:'.Permission::UPDATE_TASKS);
        Route::post('{ticket}/tasks/{task}/items', [TaskController::class, 'assignItem'])->middleware('permission:'.Permission::UPDATE_TASKS);
        Route::delete('{ticket}/tasks/{task}/items/{item}', [TaskController::class, 'removeItem'])->middleware('permission:'.Permission::UPDATE_TASKS);
    });

    Route::prefix('products')->group(function (): void {
        Route::get('/', [ProductController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{product}', [ProductController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{product}', [ProductController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::post('{product}/bikes', [ProductController::class, 'assignBikes'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::post('{product}/adjust-stock', [InventoryController::class, 'adjustProductStock'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::get('compatible', [ProductController::class, 'compatible'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{product}/calculate-price', [ProductController::class, 'calculatePrice'])->middleware('permission:'.Permission::VIEW_INVENTORY);

        Route::post('{product}/units', [ProductUnitController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{product}/units/{unit}', [ProductUnitController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{product}/units/{unit}', [ProductUnitController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('categories')->group(function (): void {
        Route::get('/', [CategoryController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{category}', [CategoryController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [CategoryController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{category}', [CategoryController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{category}', [CategoryController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('brands')->group(function (): void {
        Route::get('/', [BrandController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{brand}', [BrandController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [BrandController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{brand}', [BrandController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{brand}', [BrandController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('bikes')->group(function (): void {
        Route::get('/', [BikeController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{bike}', [BikeController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [BikeController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{bike}', [BikeController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{bike}', [BikeController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('bike-inventory')->group(function (): void {
        Route::get('/', [BikeInventoryController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{bikeInventory}', [BikeInventoryController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [BikeInventoryController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{bikeInventory}', [BikeInventoryController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{bikeInventory}', [BikeInventoryController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('services')->group(function (): void {
        Route::get('/', [ServiceController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{service}', [ServiceController::class, 'show'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('/', [ServiceController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{service}', [ServiceController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{service}', [ServiceController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('customers')->group(function (): void {
        Route::get('/', [CustomerController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::get('{customer}', [CustomerController::class, 'show'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('/', [CustomerController::class, 'store'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::put('{customer}', [CustomerController::class, 'update'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::delete('{customer}', [CustomerController::class, 'destroy'])->middleware('permission:'.Permission::CREATE_SALE);

        Route::get('{customer}/bikes', [CustomerBikeController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('{customer}/bikes', [CustomerBikeController::class, 'store'])->middleware('permission:'.Permission::CREATE_SALE);
    });

    Route::prefix('customer-bikes')->group(function (): void {
        Route::get('/', [CustomerBikeController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::get('{customerBike}', [CustomerBikeController::class, 'show'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('/', [CustomerBikeController::class, 'store'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::put('{customerBike}', [CustomerBikeController::class, 'update'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::delete('{customerBike}', [CustomerBikeController::class, 'destroy'])->middleware('permission:'.Permission::CREATE_SALE);
    });

    Route::prefix('inventory')->group(function (): void {
        Route::get('logs', [InventoryLogController::class, 'index'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('bulk-update', [InventoryController::class, 'bulkUpdate'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::post('import', [InventoryController::class, 'import'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::get('export', [InventoryController::class, 'export'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('template', [InventoryController::class, 'template'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::post('exchange-rate', [InventoryController::class, 'updateExchangeRate'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::post('adjust-stock', [InventoryController::class, 'adjustStock'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('expenses')->group(function (): void {
        Route::get('/', [ExpenseController::class, 'index'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::post('/', [ExpenseController::class, 'store'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::put('{expense}', [ExpenseController::class, 'update'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::delete('{expense}', [ExpenseController::class, 'destroy'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::post('generate-recurring', [ExpenseController::class, 'generateRecurring'])->middleware('permission:'.Permission::VIEW_REPORTS);
    });

    Route::prefix('reports')->group(function (): void {
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::get('balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::get('daily', [ReportController::class, 'daily'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::get('expenses', [ReportController::class, 'expenses'])->middleware('permission:'.Permission::VIEW_REPORTS);
        Route::get('cash-bank', [ReportController::class, 'cashBank'])->middleware('permission:'.Permission::VIEW_REPORTS);
    });

    Route::prefix('invoices')->middleware('permission:'.Permission::VIEW_REPORTS)->group(function (): void {
        Route::get('/', [InvoiceController::class, 'index']);
        Route::get('{invoice}', [InvoiceController::class, 'show']);
    });

    Route::prefix('settings')->middleware('permission:'.Permission::MANAGE_USERS)->group(function (): void {
        Route::get('/', [SettingController::class, 'index']);
        Route::put('/', [SettingController::class, 'update']);
        Route::post('exchange-rate', [SettingController::class, 'updateExchangeRate']);
    });

    Route::prefix('dashboard')->middleware('permission:'.Permission::VIEW_REPORTS)->group(function (): void {
        Route::get('metrics', [DashboardController::class, 'metrics']);
    });

    Route::get('search', SearchController::class)->middleware('permission:'.Permission::VIEW_SALES);

    Route::prefix('recovery')->middleware('permission:'.Permission::MANAGE_USERS)->group(function (): void {
        Route::get('{entity}', [RecoveryController::class, 'index']);
        Route::post('{entity}/{id}/restore', [RecoveryController::class, 'restore']);
    });

    Route::prefix('logs')->group(function (): void {
        Route::get('/', [LogController::class, 'index'])->middleware('permission:'.Permission::MANAGE_USERS);
        Route::get('{log}', [LogController::class, 'show'])->middleware('permission:'.Permission::MANAGE_USERS);
    });
});
