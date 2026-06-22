<?php

use App\Http\Controllers\Api\ExternalSaleController;
use Illuminate\Support\Facades\Route;

// throttle:120,1 = 120 requests/minute per IP. Tune to the sender's volume.
Route::middleware(['throttle:120,1', 'external.jwt'])->group(function () {
    Route::post('/external-sales', [ExternalSaleController::class, 'store']);
});
