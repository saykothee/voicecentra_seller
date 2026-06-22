<?php

use App\Models\MinSalesRequirement;
use App\Models\User;
use Database\Seeders\MinSalesRequirementSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed(MinSalesRequirementSeeder::class);
});

test('an admin can open the min sales page and see every bracket', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.configuration.min-sales'));

    $response->assertOk();
    foreach (['18–29', '30–39', '40–49', '50–59', '60–69', '70–79', '80+'] as $label) {
        $response->assertSee($label);
    }
});

test('an admin can update the min_sales values for every bracket', function () {
    $admin = User::factory()->admin()->create();
    $payload = MinSalesRequirement::orderBy('min_age')->pluck('id')
        ->mapWithKeys(fn ($id) => [$id => 99])->all();

    $this->actingAs($admin)
        ->patch(route('admin.configuration.min-sales.update'), ['min_sales' => $payload])
        ->assertRedirect(route('admin.configuration.min-sales'));

    expect(MinSalesRequirement::pluck('min_sales')->unique()->all())->toBe([99]);
});

test('the update rejects a negative min_sales', function () {
    $admin = User::factory()->admin()->create();
    $id = MinSalesRequirement::first()->id;

    $this->actingAs($admin)
        ->patch(route('admin.configuration.min-sales.update'), ['min_sales' => [$id => -3]])
        ->assertSessionHasErrors('min_sales.'.$id);
});

test('the update rejects a non-integer min_sales', function () {
    $admin = User::factory()->admin()->create();
    $id = MinSalesRequirement::first()->id;

    $this->actingAs($admin)
        ->patch(route('admin.configuration.min-sales.update'), ['min_sales' => [$id => 'abc']])
        ->assertSessionHasErrors('min_sales.'.$id);
});

test('non-admins cannot view or update min sales', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->get(route('admin.configuration.min-sales'))->assertForbidden();
    $this->actingAs($seller)
        ->patch(route('admin.configuration.min-sales.update'), ['min_sales' => []])
        ->assertForbidden();
});
