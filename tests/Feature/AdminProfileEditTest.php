<?php

use App\Models\User;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the age accessor computes years from date_of_birth', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => '1990-06-15']);

    expect($user->date_of_birth)->toBeInstanceOf(Carbon::class);
    expect($user->age)->toBe(Carbon::parse('1990-06-15')->age);
});

test('the age accessor is null when no date_of_birth is set', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => null]);

    expect($user->age)->toBeNull();
});

test('registration requires date_of_birth and stores it', function () {
    $this->post('/register', [
        'name' => 'Dob Seller',
        'email' => 'dob@example.com',
        'phone' => '555-0100',
        'date_of_birth' => '1995-03-10',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'dob@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1995-03-10');
});

test('registration rejects a missing date_of_birth', function () {
    $this->post('/register', [
        'name' => 'No Dob',
        'email' => 'nodob@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'nodob@example.com')->exists())->toBeFalse();
});

test('registration rejects an under-18 date_of_birth', function () {
    $this->post('/register', [
        'name' => 'Too Young',
        'email' => 'young@example.com',
        'phone' => '555-0100',
        'date_of_birth' => now()->subYears(17)->toDateString(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'young@example.com')->exists())->toBeFalse();
});
