<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('user role and status helpers report correctly', function () {
    $admin = User::factory()->admin()->create();
    $pending = User::factory()->pending()->create();
    $approved = User::factory()->approvedSeller()->create();

    expect($admin->isAdmin())->toBeTrue();
    expect($admin->isSeller())->toBeFalse();

    expect($pending->isSeller())->toBeTrue();
    expect($pending->isApproved())->toBeFalse();

    expect($approved->isApproved())->toBeTrue();
});

test('new registrations become pending sellers', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Seller',
        'email' => 'jane@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('seller');
    expect($user->status)->toBe('pending');
    expect($user->phone)->toBe('555-0100');
    $this->assertAuthenticated();
});

test('dashboard redirects admins to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/dashboard')->assertRedirect('/admin/dashboard');
});

test('dashboard redirects approved sellers to the seller dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect('/seller/dashboard');
});

test('dashboard redirects pending sellers to the pending page', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect(route('pending'));
});

test('the pending page renders for an authenticated seller', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/pending')->assertOk();
});

test('approved sellers can view their dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertOk()->assertSee($seller->name);
});

test('pending sellers cannot view the seller dashboard', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertRedirect(route('pending'));
});
