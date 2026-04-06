<?php

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
