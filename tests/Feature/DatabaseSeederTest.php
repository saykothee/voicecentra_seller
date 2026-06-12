<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the database seeder creates one admin and demo sellers', function () {
    $this->seed(\Database\Seeders\DatabaseSeeder::class);

    expect(User::where('role', 'admin')->count())->toBe(1);
    expect(User::where('role', 'seller')->where('status', 'pending')->count())->toBeGreaterThanOrEqual(1);
    expect(User::where('role', 'seller')->where('status', 'approved')->count())->toBeGreaterThanOrEqual(1);

    $admin = User::where('role', 'admin')->first();
    expect($admin->email)->toBe(env('ADMIN_EMAIL', 'admin@voicecentra.com'));
    expect($admin->isApproved())->toBeTrue();
});
