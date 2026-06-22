<?php

use Illuminate\Support\Facades\Route;

Route::middleware('external.jwt')->group(function () {
    // Replaced by ExternalSaleController@store in Task 3.
    Route::post('/external-sales', fn () => response()->json(['ok' => true], 200));
});
