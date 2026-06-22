<?php

use App\Models\ExternalSale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('an external sale persists with cents and boolean casts', function () {
    $seller = User::factory()->approvedSeller()->create();

    $sale = ExternalSale::create([
        'seller_id' => $seller->id,
        'sale_date' => '2026-06-20',
        'paid_at' => '2026-06-21 14:30:00',
        'amount_cents' => 4999,
        'paid' => true,
        'free_trial' => false,
    ]);

    $fresh = $sale->fresh();
    expect($fresh->amount_cents)->toBe(4999);
    expect($fresh->paid)->toBeTrue();
    expect($fresh->free_trial)->toBeFalse();
    expect($fresh->sale_date->format('Y-m-d'))->toBe('2026-06-20');
    expect($fresh->seller->id)->toBe($seller->id);
});
