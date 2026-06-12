<?php

use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a seller sees only their own payouts', function () {
    $admin = User::factory()->admin()->create();
    $sponsor = User::factory()->approvedSeller()->create();
    $seller = User::factory()->approvedSeller()->withSponsor($sponsor)->create(['name' => 'Closer Carl']);

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    // Sponsor sees their L1 payout, sourced from Carl's sale
    $this->actingAs($sponsor)->get('/seller/commissions')
        ->assertOk()->assertSee('Closer Carl')->assertSee('50.00');

    // Carl sees his own level-0 payout
    $this->actingAs($seller)->get('/seller/commissions')
        ->assertOk()->assertSee('100.00');
});

test('the seller dashboard shows earnings cards and the referral link', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 50_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    $this->actingAs($seller)->get('/seller/dashboard')
        ->assertOk()
        ->assertSee(__('messages.total_earned'))
        ->assertSee('50.00')                       // 10% of $500
        ->assertSee($seller->referral_code);
});

test('reversed payouts are excluded from earnings totals', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 50_000]);
    $distributor = app(CommissionDistributor::class);
    $distributor->distribute($sale, $admin);
    $distributor->refund($sale->refresh());

    $this->actingAs($seller)->get('/seller/dashboard')
        ->assertOk()
        ->assertSee('0.00');
});
