<?php

use App\Models\User;
use App\Services\MinSalesLookup;
use Database\Seeders\MinSalesRequirementSeeder;

uses(Tests\TestCase::class, \Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MinSalesRequirementSeeder::class);
    $this->lookup = new MinSalesLookup();
});

function userAged(?int $years): User
{
    return User::factory()->create([
        'date_of_birth' => $years === null ? null : now()->subYears($years)->subDays(5),
    ]);
}

test('it matches each age bracket and returns its min_sales', function () {
    expect($this->lookup->forUser(userAged(20)))
        ->toMatchArray(['age' => 20, 'label' => '18–29', 'min_sales' => 10, 'matched' => true]);
    expect($this->lookup->forUser(userAged(35))['min_sales'])->toBe(8);
    expect($this->lookup->forUser(userAged(48))['min_sales'])->toBe(6);
    expect($this->lookup->forUser(userAged(60))['min_sales'])->toBe(4);
    expect($this->lookup->forUser(userAged(85)))
        ->toMatchArray(['label' => '80+', 'min_sales' => 2, 'matched' => true]);
});

test('a user with no date_of_birth falls back to multiplier 1', function () {
    expect($this->lookup->forUser(userAged(null)))
        ->toMatchArray(['age' => null, 'label' => null, 'min_sales' => 1, 'matched' => false]);
});

test('an age below all brackets falls back to multiplier 1', function () {
    expect($this->lookup->forUser(userAged(15)))
        ->toMatchArray(['min_sales' => 1, 'matched' => false]);
});
