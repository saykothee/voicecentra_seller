<?php

use App\Models\User;
use Database\Seeders\MinSalesRequirementSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => $this->seed(MinSalesRequirementSeeder::class));

test('guests are redirected and pending sellers are forbidden', function () {
    $this->get('/calculator-2')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->pending()->create())->get('/calculator-2')->assertForbidden();
});

test('admins and approved sellers can open calculator 2.0 and see the seller dropdown', function () {
    $target = User::factory()->approvedSeller()->create(['name' => 'Pickable Seller']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/calculator-2')
        ->assertOk()
        ->assertSee(__('messages.calculator_2_title'))
        ->assertSee('Pickable Seller');

    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/calculator-2')->assertOk();
});
