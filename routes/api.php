<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\RecoveryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
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
        Route::post('/', [SaleController::class, 'store'])->middleware('permission:'.Permission::CREATE_SALE);
        Route::post('{sale}/items', [SaleController::class, 'addItem'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::post('{sale}/payments', [SaleController::class, 'addPayment'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::post('{sale}/complete', [SaleController::class, 'complete'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::get('{sale}/returns', [ReturnController::class, 'index'])->middleware('permission:'.Permission::VIEW_SALES);
        Route::post('{sale}/returns', [ReturnController::class, 'store'])->middleware('permission:'.Permission::EDIT_SALE);
        Route::post('{sale}/return', [SaleController::class, 'returnSale'])->middleware('permission:'.Permission::EDIT_SALE);
    });

    Route::prefix('tickets')->group(function (): void {
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
        Route::post('/', [ProductController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{product}', [ProductController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::post('{product}/bikes', [ProductController::class, 'assignBikes'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::get('compatible', [ProductController::class, 'compatible'])->middleware('permission:'.Permission::VIEW_INVENTORY);
        Route::get('{product}/calculate-price', [ProductController::class, 'calculatePrice'])->middleware('permission:'.Permission::VIEW_INVENTORY);

        Route::post('{product}/units', [ProductUnitController::class, 'store'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::put('{product}/units/{unit}', [ProductUnitController::class, 'update'])->middleware('permission:'.Permission::EDIT_INVENTORY);
        Route::delete('{product}/units/{unit}', [ProductUnitController::class, 'destroy'])->middleware('permission:'.Permission::EDIT_INVENTORY);
    });

    Route::prefix('inventory')->group(function (): void {
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

    Route::prefix('recovery')->middleware('permission:'.Permission::MANAGE_USERS)->group(function (): void {
        Route::get('{entity}', [RecoveryController::class, 'index']);
        Route::post('{entity}/{id}/restore', [RecoveryController::class, 'restore']);
    });

    Route::prefix('logs')->group(function (): void {
        Route::get('/', [LogController::class, 'index'])->middleware('permission:'.Permission::MANAGE_USERS);
        Route::get('{log}', [LogController::class, 'show'])->middleware('permission:'.Permission::MANAGE_USERS);
    });
});
