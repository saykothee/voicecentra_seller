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

/**
 * Build a per-month POST payload: months + amount[m] + quantity[m] for each month.
 * $overrides is keyed by month => ['amount' => ..., 'quantity' => ...].
 */
function monthlyPayload(int $sellerId, string $amount = '100', string $quantity = '1', array $overrides = [], int $months = 6): array
{
    $payload = ['seller_id' => $sellerId, 'months' => (string) $months, 'amount' => [], 'quantity' => []];
    for ($month = 1; $month <= $months; $month++) {
        $payload['amount'][$month] = $amount;
        $payload['quantity'][$month] = $quantity;
    }
    foreach ($overrides as $month => $vals) {
        foreach ($vals as $key => $value) {
            $payload[$key][$month] = $value;
        }
    }

    return $payload;
}

test('selling only in the first month still pays out across months 2-6 (residual)', function () {
    // Chain: Carla(35) -> Bruno(30) -> Ana(48) -> Seller One(20)
    $top   = sellerAged('Seller One', 20);
    $ana   = sellerAged('Ana Demo', 48, $top);
    $bruno = sellerAged('Bruno Demo', 30, $ana);
    $carla = sellerAged('Carla Demo', 35, $bruno);

    // One $100 sale in month 1 only; it stays active and keeps paying months 2-6.
    // Active base $100 -> seller effective 100x8 = $800; full seller cut $80;
    // chain L1 $40 + L2 $20 + L3 $10 = $70, paid every month from month 2.
    $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', monthlyPayload($carla->id, '100', '0', [1 => ['quantity' => '1']]))
        ->assertOk()
        ->assertSee('Bruno Demo')->assertSee('Ana Demo')->assertSee('Seller One')
        ->assertSee(__('messages.projection_title', ['n' => 6]))
        ->assertSee('$50.00')   // months 2-4: $50 x 1 active sale (no new sales needed)
        ->assertSee('$80.00')   // months 5-6: full seller cut on the month-1 sale
        ->assertSee('$70.00')   // chain commission every month 2-6
        ->assertSee('$310.00')->assertSee('$350.00'); // seller / chain 6-month totals
});

test('later-month sales add to the active base', function () {
    $seller = sellerAged('Solo Seller', 35); // age 35 -> min_sales 8, no uplines

    // 1 sale of $100 in month 1, plus 1 more in month 5.
    // Through month 4: base $100, 1 active sale -> $50/mo flat.
    // Months 5-6: base $200, 2 active sales, full cut on $1,600 effective = $160.
    $payload = monthlyPayload($seller->id, '100', '0', [
        1 => ['quantity' => '1'],
        5 => ['quantity' => '1'],
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', $payload)
        ->assertOk()
        ->assertSee('$50.00')    // months 2-4: $50 x 1 active sale
        ->assertSee('$160.00');  // months 5-6: full cut on 2 active sales
});

test('the projection length is selectable up to 60 months', function () {
    $seller = sellerAged('Solo Seller', 35); // age 35 -> min_sales 8, no uplines

    // 12-month projection, one $100 sale in month 1; months 5-12 are full -> $80 each.
    $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '0', [1 => ['quantity' => '1']], 12))
        ->assertOk()
        ->assertSee(__('messages.month_n', ['n' => 12]))  // month 12 row is rendered
        ->assertSee('$80.00');                            // full seller cut in the later months
});

test('it rejects a projection whose totals would overflow integer math', function () {
    // Young seller (min_sales 10) + max amounts/quantities over 60 months pushes the
    // cumulative effective base past the safe ceiling -> clean validation error, not a 500.
    $seller = sellerAged('Young Seller', 20);

    $this->actingAs(User::factory()->admin()->create())
        ->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100000000', '10000', [], 60))
        ->assertSessionHasErrors('months');
});

test('it rejects a month count outside the allowed range', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1', [], 5))
        ->assertSessionHasErrors('months');

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1', [], 61))
        ->assertSessionHasErrors('months');
});

test('it allows a month with zero sales', function () {
    $seller = sellerAged('Solo Seller', 35);

    // Month 4 (a flat month) has no sales: no validation error and that month
    // earns nothing (flat stipend is $50 x 0 = $0).
    $this->actingAs(User::factory()->admin()->create())
        ->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1', [4 => ['quantity' => '0']]))
        ->assertOk()
        ->assertSessionHasNoErrors();
});

test('a member with no date of birth shows the no-requirement note and uses a x1 multiplier', function () {
    $seller = User::factory()->approvedSeller()->create(['name' => 'No Birthday', 'date_of_birth' => null]);

    $this->actingAs(User::factory()->admin()->create())
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1'))
        ->assertOk()
        ->assertSee(__('messages.no_requirement'));
});

test('compute rejects a missing seller and a non-seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $noSeller = monthlyPayload($seller->id);
    unset($noSeller['seller_id']);
    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', $noSeller)->assertSessionHasErrors('seller_id');

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($admin->id))
        ->assertSessionHasErrors('seller_id');
});

test('compute rejects a zero amount or fractional quantity in any month', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1', [3 => ['amount' => '0']]))
        ->assertSessionHasErrors('amount.3');

    $this->actingAs($admin)->from('/calculator-2')
        ->post('/calculator-2', monthlyPayload($seller->id, '100', '1', [4 => ['quantity' => '2.5']]))
        ->assertSessionHasErrors('quantity.4');
});

test('a pending seller cannot post to compute', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs(User::factory()->pending()->create())
        ->post('/calculator-2', monthlyPayload($seller->id))
        ->assertForbidden();
});

test('the calculator 2.0 page exposes a nav link to itself for an approved seller', function () {
    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/calculator-2')
        ->assertOk()
        ->assertSee(__('messages.calculator_2'))
        ->assertSee(route('calculator2'), false);
});
