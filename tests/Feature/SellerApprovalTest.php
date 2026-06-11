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
