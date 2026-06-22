<?php

use App\Models\MinSalesRequirement;
use Database\Seeders\MinSalesRequirementSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the seeder creates the seven fixed brackets', function () {
    $this->seed(MinSalesRequirementSeeder::class);

    expect(MinSalesRequirement::count())->toBe(7);

    $first = MinSalesRequirement::orderBy('min_age')->first();
    expect($first->min_age)->toBe(18);
    expect($first->max_age)->toBe(29);

    $last = MinSalesRequirement::orderByDesc('min_age')->first();
    expect($last->min_age)->toBe(80);
    expect($last->max_age)->toBeNull();
});

test('the seeder is idempotent', function () {
    $this->seed(MinSalesRequirementSeeder::class);
    $this->seed(MinSalesRequirementSeeder::class);

    expect(MinSalesRequirement::count())->toBe(7);
});

test('forAge returns the matching bracket including boundaries and the open-ended top', function () {
    $this->seed(MinSalesRequirementSeeder::class);

    expect(MinSalesRequirement::forAge(18)->first()->min_age)->toBe(18);
    expect(MinSalesRequirement::forAge(29)->first()->min_age)->toBe(18);
    expect(MinSalesRequirement::forAge(30)->first()->min_age)->toBe(30);
    expect(MinSalesRequirement::forAge(79)->first()->min_age)->toBe(70);
    expect(MinSalesRequirement::forAge(80)->first()->min_age)->toBe(80);
    expect(MinSalesRequirement::forAge(95)->first()->min_age)->toBe(80);
});

test('label renders a hyphenated range and an open-ended top', function () {
    $bracket = MinSalesRequirement::create(['min_age' => 18, 'max_age' => 29, 'min_sales' => 10]);
    $top = MinSalesRequirement::create(['min_age' => 80, 'max_age' => null, 'min_sales' => 2]);

    expect($bracket->label())->toBe('18–29');
    expect($top->label())->toBe('80+');
});
