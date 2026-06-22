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
