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
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{user}/approve', [AdminSellerController::class, 'approve'])->name('sellers.approve');
    Route::patch('/sellers/{user}/reject', [AdminSellerController::class, 'reject'])->name('sellers.reject');
});

Route::middleware(['auth', 'seller.approved'])->group(function () {
    Route::get('/seller/dashboard', SellerDashboardController::class)->name('seller.dashboard');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
