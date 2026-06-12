<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a seller sees their own downline and sponsor but not other branches', function () {
    $sponsor = User::factory()->approvedSeller()->create(['name' => 'Root Sponsor']);
    $me = User::factory()->approvedSeller()->withSponsor($sponsor)->create();
    $myChild = User::factory()->approvedSeller()->withSponsor($me)->create(['name' => 'My Recruit']);
    $sibling = User::factory()->approvedSeller()->withSponsor($sponsor)->create(['name' => 'Sibling Seller']);

    $this->actingAs($me)->get('/seller/network')
        ->assertOk()
        ->assertSee('My Recruit')
        ->assertSee('Root Sponsor')   // sponsor line only
        ->assertDontSee('Sibling Seller');
});

test('a top-level seller sees the top-level message', function () {
    $me = User::factory()->approvedSeller()->create();

    $this->actingAs($me)->get('/seller/network')
        ->assertOk()
        ->assertSee(__('messages.top_level_seller'));
});

test('admin network shows every root and branch', function () {
    $admin = User::factory()->admin()->create();
    $r1 = User::factory()->approvedSeller()->create(['name' => 'Root One']);
    User::factory()->approvedSeller()->withSponsor($r1)->create(['name' => 'Child One']);
    User::factory()->approvedSeller()->create(['name' => 'Root Two']);

    $this->actingAs($admin)->get('/admin/network')
        ->assertOk()
        ->assertSee('Root One')->assertSee('Child One')->assertSee('Root Two');
});

test('pending sellers and non-admins are gated', function () {
    $pending = User::factory()->pending()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($pending)->get('/seller/network')->assertRedirect(route('pending'));
    $this->actingAs($seller)->get('/admin/network')->assertForbidden();
});
