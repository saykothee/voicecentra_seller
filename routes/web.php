<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSellerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\PendingController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SellerDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => view('landing'))->name('landing');

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

Route::get('/pending', [PendingController::class, 'show'])
    ->middleware('auth')->name('pending');

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/network', \App\Http\Controllers\Admin\AdminNetworkController::class)->name('network');
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{user}/approve', [AdminSellerController::class, 'approve'])->name('sellers.approve');
    Route::patch('/sellers/{user}/reject', [AdminSellerController::class, 'reject'])->name('sellers.reject');
    Route::get('/sellers/{user}/edit', [AdminSellerController::class, 'edit'])->name('sellers.edit');
    Route::patch('/sellers/{user}', [AdminSellerController::class, 'update'])->name('sellers.update');
    Route::get('/sellers/{user}/sponsor', [AdminSellerController::class, 'editSponsor'])->name('sellers.sponsor.edit');
    Route::patch('/sellers/{user}/sponsor', [AdminSellerController::class, 'updateSponsor'])->name('sellers.sponsor.update');
    Route::get('/sales', [\App\Http\Controllers\Admin\AdminSaleController::class, 'index'])->name('sales.index');
    Route::patch('/sales/{sale}/approve', [\App\Http\Controllers\Admin\AdminSaleController::class, 'approve'])->name('sales.approve');
    Route::patch('/sales/{sale}/reject', [\App\Http\Controllers\Admin\AdminSaleController::class, 'reject'])->name('sales.reject');
    Route::patch('/sales/{sale}/refund', [\App\Http\Controllers\Admin\AdminSaleController::class, 'refund'])->name('sales.refund');
    Route::get('/bonus-pool', \App\Http\Controllers\Admin\AdminBonusPoolController::class)->name('bonus-pool');
    Route::get('/configuration/min-sales', [\App\Http\Controllers\Admin\AdminMinSalesController::class, 'index'])->name('configuration.min-sales');
    Route::patch('/configuration/min-sales', [\App\Http\Controllers\Admin\AdminMinSalesController::class, 'update'])->name('configuration.min-sales.update');
});

Route::middleware(['auth', 'seller.approved'])->group(function () {
    Route::get('/seller/dashboard', SellerDashboardController::class)->name('seller.dashboard');
    Route::get('/seller/commissions', \App\Http\Controllers\SellerCommissionController::class)->name('seller.commissions');
    Route::get('/seller/network', \App\Http\Controllers\SellerNetworkController::class)->name('seller.network');
    Route::get('/seller/sales', [\App\Http\Controllers\SellerSaleController::class, 'index'])->name('seller.sales.index');
    Route::post('/seller/sales', [\App\Http\Controllers\SellerSaleController::class, 'store'])->name('seller.sales.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/calculator', [\App\Http\Controllers\CalculatorController::class, 'show'])->name('calculator');
    Route::post('/calculator', [\App\Http\Controllers\CalculatorController::class, 'compute'])->name('calculator.compute');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
