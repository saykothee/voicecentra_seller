<?php

use App\Http\Controllers\Api\ExternalSaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('external.jwt')->group(function () {
    Route::post('/external-sales', [ExternalSaleController::class, 'store']);
});
