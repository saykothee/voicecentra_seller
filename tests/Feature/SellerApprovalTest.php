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
