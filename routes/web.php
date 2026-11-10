<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\GastoController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\UserController;
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
    Route::get('/admin/accounting/export', [AccountingController::class, 'exportCsv'])->name('admin.accounting.export');
    Route::get('/admin/gastos', [GastoController::class, 'index'])->name('admin.gastos.index');
    Route::post('/admin/gastos', [GastoController::class, 'store'])->name('admin.gastos.store');
    Route::delete('/admin/gastos/{gasto}', [GastoController::class, 'destroy'])->name('admin.gastos.destroy');
    Route::get('/admin/catalog', [CatalogController::class, 'index'])->name('admin.catalog.index');
    Route::middleware('feature:gestion_usuarios')->group(function () {
        Route::get('/admin/usuarios', [UserController::class, 'index'])->name('admin.usuarios.index');
        Route::post('/admin/usuarios', [UserController::class, 'store'])->name('admin.usuarios.store');
        Route::patch('/admin/usuarios/{user}', [UserController::class, 'update'])->name('admin.usuarios.update');
        Route::delete('/admin/usuarios/{user}', [UserController::class, 'destroy'])->name('admin.usuarios.destroy');
    });
    Route::get('/admin/inventory', [InventoryController::class, 'index'])->name('admin.inventory.index');
    Route::patch('/admin/inventory/{dish}/tracking', [InventoryController::class, 'updateTracking'])->name('admin.inventory.tracking');
    Route::post('/admin/inventory/{dish}/movements', [InventoryController::class, 'storeMovement'])->name('admin.inventory.movements.store');
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/sales', [PosController::class, 'storeSale'])->name('pos.sales.store');

    Route::post('/dishes', [DishController::class, 'store'])->name('dishes.store');
    Route::post('/dishes/{dish}', [DishController::class, 'update'])->name('dishes.update');
    Route::patch('/dishes/{dish}/toggle', [DishController::class, 'toggleAvailability'])->name('dishes.toggle');
    Route::delete('/dishes/{dish}', [DishController::class, 'destroy'])->name('dishes.destroy');
    Route::patch('/dishes/{dish}/restore', [DishController::class, 'restore'])->name('dishes.restore');

    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
});
