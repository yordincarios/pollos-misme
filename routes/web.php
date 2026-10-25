<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentSettingController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\StallController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('admin.index');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    Route::get('/admin', [AdminController::class, 'index'])->name('admin.index');
    Route::get('/admin/accounting', [AccountingController::class, 'index'])->name('admin.accounting');
    Route::get('/admin/accounting/print', [AccountingController::class, 'printReport'])->name('admin.accounting.print');
    Route::get('/admin/stalls', [StallController::class, 'index'])->name('admin.stalls.index');
    Route::post('/admin/stalls', [StallController::class, 'store'])->name('admin.stalls.store');
    Route::patch('/admin/stalls/{stall}', [StallController::class, 'update'])->name('admin.stalls.update');
    Route::patch('/admin/stalls/{stall}/availability', [StallController::class, 'updateAvailability'])->name('admin.stalls.availability');
    Route::get('/admin/orders', [OrderController::class, 'index'])->name('admin.orders.index');
    Route::post('/admin/orders', [OrderController::class, 'store'])->name('admin.orders.store');
    Route::patch('/admin/orders/{order}/status', [OrderController::class, 'updateStatus'])->name('admin.orders.status');
    Route::post('/admin/orders/{order}/payment', [OrderController::class, 'markPaid'])->name('admin.orders.payment');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/sales', [PosController::class, 'storeSale'])->name('pos.sales.store');
    Route::post('/payment-settings/qr', [PaymentSettingController::class, 'updateQr'])->name('payment-settings.qr.update');
    Route::delete('/payment-settings/qr', [PaymentSettingController::class, 'destroyQr'])->name('payment-settings.qr.destroy');

    Route::post('/dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::post('/dishes/{dish}', [DishController::class, 'update'])->name('dishes.update');
    Route::patch('/dishes/{dish}/toggle', [DishController::class, 'toggleAvailability'])->name('dishes.toggle');
    Route::delete('/dishes/{dish}', [DishController::class, 'destroy'])->name('dishes.destroy');
    Route::patch('/dishes/{dish}/restore', [DishController::class, 'restore'])->name('dishes.restore');

    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});
