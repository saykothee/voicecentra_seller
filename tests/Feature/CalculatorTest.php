<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admins and approved sellers can open the calculator', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/calculator')->assertOk();
    $this->actingAs(User::factory()->approvedSeller()->create())->get('/calculator')->assertOk();
});

test('pending sellers and guests cannot use the calculator', function () {
    $this->get('/calculator')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->pending()->create())->get('/calculator')->assertForbidden();
    $this->actingAs(User::factory()->pending()->create())
        ->post('/calculator', ['amount' => 100, 'uplines' => 3])->assertForbidden();
});

test('the calculator computes a full active chain for $1000', function () {
    $seller = User::factory()->approvedSeller()->create();

    $response = $this->actingAs($seller)->post('/calculator', [
        'amount' => '1000',
        'uplines' => 9,
        'active' => [4 => '1', 5 => '1', 6 => '1', 7 => '1', 8 => '1', 9 => '1'],
    ]);

    $response->assertOk()
        ->assertSee('100.00')   // seller cut $100.00
        ->assertSee('50.00')    // L1 $50.00
        ->assertSee('199.80');  // total charge $199.80
});

test('inactive levels route to the pool in the results', function () {
    $seller = User::factory()->approvedSeller()->create();

    // Omit 'active' key entirely — same semantics as an empty array but avoids
    // PHP stripping an empty array literal from the POST payload.
    $this->actingAs($seller)->post('/calculator', [
        'amount' => '1000',
        'uplines' => 9,
    ])->assertOk()
      ->assertSee(__('messages.dest_pool_inactive'));
});
