<?php

use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductUnitController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

Route::prefix('sales')->group(function (): void {
    Route::post('/', [SaleController::class, 'store']);
    Route::post('{sale}/items', [SaleController::class, 'addItem']);
    Route::post('{sale}/payments', [SaleController::class, 'addPayment']);
    Route::post('{sale}/complete', [SaleController::class, 'complete']);
    Route::post('{sale}/return', [SaleController::class, 'returnSale']);
});

Route::prefix('tickets')->group(function (): void {
    Route::post('/', [TicketController::class, 'store']);
    Route::post('{ticket}/start', [TicketController::class, 'start']);
    Route::post('{ticket}/complete', [TicketController::class, 'complete']);
    Route::post('{ticket}/reopen', [TicketController::class, 'reopen']);
    Route::post('{ticket}/notes', [TicketController::class, 'addNote']);

    Route::post('{ticket}/tasks', [TaskController::class, 'store']);
    Route::put('{ticket}/tasks/{task}', [TaskController::class, 'update']);
    Route::delete('{ticket}/tasks/{task}', [TaskController::class, 'destroy']);
    Route::post('{ticket}/tasks/{task}/items', [TaskController::class, 'assignItem']);
    Route::delete('{ticket}/tasks/{task}/items/{item}', [TaskController::class, 'removeItem']);
});

Route::prefix('products')->group(function (): void {
    Route::post('/', [ProductController::class, 'store']);
    Route::put('{product}', [ProductController::class, 'update']);
    Route::post('{product}/bikes', [ProductController::class, 'assignBikes']);
    Route::get('compatible', [ProductController::class, 'compatible']);
    Route::get('{product}/calculate-price', [ProductController::class, 'calculatePrice']);

    Route::post('{product}/units', [ProductUnitController::class, 'store']);
    Route::put('{product}/units/{unit}', [ProductUnitController::class, 'update']);
    Route::delete('{product}/units/{unit}', [ProductUnitController::class, 'destroy']);
});

Route::prefix('inventory')->group(function (): void {
    Route::post('bulk-update', [InventoryController::class, 'bulkUpdate']);
    Route::post('import', [InventoryController::class, 'import']);
    Route::get('export', [InventoryController::class, 'export']);
    Route::get('template', [InventoryController::class, 'template']);
    Route::post('exchange-rate', [InventoryController::class, 'updateExchangeRate']);
    Route::post('adjust-stock', [InventoryController::class, 'adjustStock']);
});

Route::prefix('expenses')->group(function (): void {
    Route::get('/', [ExpenseController::class, 'index']);
    Route::post('/', [ExpenseController::class, 'store']);
    Route::put('{expense}', [ExpenseController::class, 'update']);
    Route::delete('{expense}', [ExpenseController::class, 'destroy']);
    Route::post('generate-recurring', [ExpenseController::class, 'generateRecurring']);
});

Route::prefix('reports')->group(function (): void {
    Route::get('profit-loss', [ReportController::class, 'profitLoss']);
    Route::get('balance-sheet', [ReportController::class, 'balanceSheet']);
    Route::get('daily', [ReportController::class, 'daily']);
    Route::get('expenses', [ReportController::class, 'expenses']);
    Route::get('cash-bank', [ReportController::class, 'cashBank']);
});

Route::prefix('logs')->group(function (): void {
    Route::get('/', [LogController::class, 'index']);
    Route::get('{log}', [LogController::class, 'show']);
});
