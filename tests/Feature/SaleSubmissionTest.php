<?php

use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('an approved seller can submit a sale that lands pending', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '1234.56',
        'sold_at' => now()->toDateString(),
        'notes' => 'Voice AI for a dental clinic',
    ])->assertRedirect();

    $sale = Sale::first();
    expect($sale->seller_id)->toBe($seller->id);
    expect($sale->amount_cents)->toBe(123_456);
    expect($sale->status)->toBe('pending');
});

test('a future sale date is rejected', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '100',
        'sold_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('sold_at');
});

test('pending sellers cannot reach the sales page', function () {
    $seller = User::factory()->pending()->create();

    $this->actingAs($seller)->get('/seller/sales')->assertRedirect(route('pending'));
    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '100', 'sold_at' => now()->toDateString(),
    ])->assertRedirect(route('pending'));
    expect(Sale::count())->toBe(0);
});

test('a seller only sees their own sales', function () {
    $a = User::factory()->approvedSeller()->create();
    $b = User::factory()->approvedSeller()->create();
    Sale::factory()->create(['seller_id' => $a->id, 'notes' => 'mine-note']);
    Sale::factory()->create(['seller_id' => $b->id, 'notes' => 'other-note']);

    $this->actingAs($a)->get('/seller/sales')
        ->assertOk()
        ->assertSee('mine-note')
        ->assertDontSee('other-note');
});
