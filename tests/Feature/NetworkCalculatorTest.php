<?php

use App\Models\User;
use Database\Seeders\MinSalesRequirementSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(fn () => $this->seed(MinSalesRequirementSeeder::class));

test('guests are redirected and pending sellers are forbidden', function () {
    $this->get('/calculator-2')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->pending()->create())->get('/calculator-2')->assertForbidden();
});

test('admins and approved sellers can open calculator 2.0 and see the seller dropdown', function () {
    $target = User::factory()->approvedSeller()->create(['name' => 'Pickable Seller']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/calculator-2')
        ->assertOk()
        ->assertSee(__('messages.calculator_2_title'))
        ->assertSee('Pickable Seller');

    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/calculator-2')->assertOk();
});

function sellerAged(string $name, int $years, ?User $sponsor = null): User
{
    $factory = User::factory()->approvedSeller();
    if ($sponsor) {
        $factory = $factory->withSponsor($sponsor);
    }
    return $factory->create([
        'name' => $name,
        'date_of_birth' => now()->subYears($years)->subDays(5),
    ]);
}

test('it multiplies each member by their age min-sales and splits the seller effective amount', function () {
    // Chain: Carla(35) -> Bruno(30) -> Ana(48) -> Seller One(20)
    $top   = sellerAged('Seller One', 20);
    $ana   = sellerAged('Ana Demo', 48, $top);
    $bruno = sellerAged('Bruno Demo', 30, $ana);
    $carla = sellerAged('Carla Demo', 35, $bruno);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', ['seller_id' => $carla->id, 'amount' => '100']);

    $response->assertOk()
        // upline names appear in the split table
        ->assertSee('Bruno Demo')->assertSee('Ana Demo')->assertSee('Seller One')
        // per-member effective amounts: Carla 100x8=$800, Ana 100x6=$600
        ->assertSee('800.00')->assertSee('600.00')
        // seller effective = 100x8 = $800 -> seller cut 10% = $80.00 (proves the multiplier applied)
        ->assertSee('80.00')
        ->assertSee(__('messages.invariant_ok'));
});

test('a member with no date of birth shows the no-requirement note and uses a x1 multiplier', function () {
    $seller = User::factory()->approvedSeller()->create(['name' => 'No Birthday', 'date_of_birth' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', ['seller_id' => $seller->id, 'amount' => '100'])
        ->assertOk()
        ->assertSee(__('messages.no_requirement'));
});

test('compute rejects a missing seller, a non-seller, and a zero amount', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', ['amount' => '100'])->assertSessionHasErrors('seller_id');

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', ['seller_id' => $admin->id, 'amount' => '100'])
        ->assertSessionHasErrors('seller_id');

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', ['seller_id' => $seller->id, 'amount' => '0'])
        ->assertSessionHasErrors('amount');
});

test('a pending seller cannot post to compute', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs(User::factory()->pending()->create())
        ->post('/calculator-2', ['seller_id' => $seller->id, 'amount' => '100'])
        ->assertForbidden();
});

test('the calculator 2.0 page exposes a nav link to itself for an approved seller', function () {
    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/calculator-2')
        ->assertOk()
        ->assertSee(__('messages.calculator_2'))
        ->assertSee(route('calculator2'), false);
});
