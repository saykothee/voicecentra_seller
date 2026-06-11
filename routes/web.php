<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

Route::get('/pending', [PendingController::class, 'show'])
    ->middleware('auth')->name('pending');

// Stubs replaced by controllers in Tasks 6 (seller.dashboard) and 7 (admin.dashboard)
Route::get('/seller/dashboard', fn () => '')->middleware('auth')->name('seller.dashboard');
Route::get('/admin/dashboard', fn () => '')->middleware('auth')->name('admin.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
