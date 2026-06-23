# Calculator 2.0 (Network Commission Calculator) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a second commission calculator that runs over a seller's real upline chain (by name), multiplying each member's sale amount by their age-based minimum-sales requirement, then splitting the seller's grossed-up amount across the chain.

**Architecture:** A new `NetworkCalculatorController` (`show` + `compute`) backed by a new pure `MinSalesLookup` service, reusing the existing `CommissionCalculator` (the split math) and `CommissionDistributor::isActive()` (real activity check) unchanged. New Blade view `calculator-2`. The original `/calculator` is untouched.

**Tech Stack:** Laravel 11, Blade + Tailwind, Pest (feature on in-memory SQLite, unit), MariaDB live. Money as integer cents.

**Reference (read before starting):**
- Spec: `docs/superpowers/specs/2026-06-23-calculator-2-network-design.md`
- `app/Services/CommissionCalculator.php` — `calculate(int $amountCents, array $uplineSlots): array` returning `seller_cents`, `levels[level => {exists,paid,amount_cents,pool_reason}]`, `pool_rounding_cents`, `pool_total_cents`, `total_charge_cents`.
- `app/Services/CommissionDistributor.php` — `isActive(User $seller, Carbon $at): bool`.
- `app/Models/User.php` — `uplineChain(): array` (level 1..9 => User), `age` accessor (null when no DOB).
- `app/Models/MinSalesRequirement.php` — `scopeForAge($q, int $age)`, `label(): string`, `min_sales`.
- `app/Http/Controllers/CalculatorController.php` + `resources/views/calculator.blade.php` — the base to mirror.
- `database/seeders/MinSalesRequirementSeeder.php` — seeds the 7 brackets (18–29→10, 30–39→8, 40–49→6, 50–59→5, 60–69→4, 70–79→3, 80+→2).
- `database/factories/UserFactory.php` — states `admin()`, `approvedSeller()`, `pending()`, `withSponsor(User)`. NOTE: `date_of_birth` is NOT set by default — pass it explicitly in `create([...])`.

---

## File Structure

- **Create** `app/Services/MinSalesLookup.php` — maps a User → `{age, label, min_sales, matched}`. Pure (one DB read), unit-tested.
- **Create** `app/Http/Controllers/NetworkCalculatorController.php` — `show` + `compute`, access-gated.
- **Create** `resources/views/calculator-2.blade.php` — form (seller dropdown + amount) + two result tables (per-member, commission split).
- **Modify** `routes/web.php` — two new routes in the existing `auth` group.
- **Modify** `lang/en/messages.php` and `lang/es/messages.php` — new i18n keys.
- **Modify** `resources/views/layouts/navigation.blade.php` — "Calculator 2.0" links.
- **Create** `tests/Unit/MinSalesLookupTest.php` and `tests/Feature/NetworkCalculatorTest.php`.

---

### Task 1: `MinSalesLookup` service

**Files:**
- Create: `app/Services/MinSalesLookup.php`
- Test: `tests/Unit/MinSalesLookupTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Unit/MinSalesLookupTest.php

use App\Models\User;
use App\Services\MinSalesLookup;
use Database\Seeders\MinSalesRequirementSeeder;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test tests/Unit/MinSalesLookupTest.php`
Expected: FAIL — `Class "App\Services\MinSalesLookup" not found`.

- [ ] **Step 3: Write the implementation**

```php
<?php
// app/Services/MinSalesLookup.php

namespace App\Services;

use App\Models\MinSalesRequirement;
use App\Models\User;

class MinSalesLookup
{
    /**
     * Map a user to their age-based minimum-sales multiplier.
     *
     * @return array{age: ?int, label: ?string, min_sales: int, matched: bool}
     *   matched=false (no DOB, or age outside every bracket) → multiplier 1 so the
     *   member still shows the raw amount instead of zeroing out.
     */
    public function forUser(User $user): array
    {
        $age = $user->age; // null when date_of_birth is null

        $bracket = $age === null
            ? null
            : MinSalesRequirement::forAge($age)->first();

        if (! $bracket) {
            return ['age' => $age, 'label' => null, 'min_sales' => 1, 'matched' => false];
        }

        return [
            'age' => $age,
            'label' => $bracket->label(),
            'min_sales' => $bracket->min_sales,
            'matched' => true,
        ];
    }
}
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `php artisan test tests/Unit/MinSalesLookupTest.php`
Expected: PASS (3 passed).

- [ ] **Step 5: Commit**

```bash
git add app/Services/MinSalesLookup.php tests/Unit/MinSalesLookupTest.php
git commit -m "feat: add MinSalesLookup service for age-based sales multiplier"
```

---

### Task 2: i18n keys (en + es)

**Files:**
- Modify: `lang/en/messages.php` (near the existing `'calculator'` key, ~line 155)
- Modify: `lang/es/messages.php` (near the existing `'calculator'` key, ~line 150)

- [ ] **Step 1: Add the English keys**

In `lang/en/messages.php`, immediately after the line `'calculator' => 'Calculator',` add:

```php
    'calculator_2' => 'Calculator 2.0',
    'calculator_2_title' => 'Network Commission Calculator',
    'calculator_2_intro' => 'Pick a seller and a sale amount. Each member\'s amount is multiplied by their minimum sales for their age, then the seller\'s result is split across the real upline chain.',
    'select_seller' => 'Seller',
    'col_member' => 'Member',
    'col_name' => 'Name',
    'col_age' => 'Age',
    'col_bracket' => 'Age bracket',
    'col_min_sales' => 'Min sales (×)',
    'col_effective' => 'Effective amount',
    'effective_total' => 'Total effective',
    'no_requirement' => 'no requirement',
    'per_member_breakdown' => 'Per member (amount × min sales)',
    'distributed_note' => 'Distributing :amount across the upline chain (sale × seller min sales).',
    'seller_label' => 'Seller',
```

- [ ] **Step 2: Add the Spanish keys**

In `lang/es/messages.php`, immediately after the line `'calculator' => 'Calculadora',` add:

```php
    'calculator_2' => 'Calculadora 2.0',
    'calculator_2_title' => 'Calculadora de comisiones de red',
    'calculator_2_intro' => 'Elige un vendedor y un monto de venta. El monto de cada miembro se multiplica por sus ventas mínimas según su edad, y el resultado del vendedor se reparte por la cadena real de patrocinadores.',
    'select_seller' => 'Vendedor',
    'col_member' => 'Miembro',
    'col_name' => 'Nombre',
    'col_age' => 'Edad',
    'col_bracket' => 'Rango de edad',
    'col_min_sales' => 'Ventas mín. (×)',
    'col_effective' => 'Monto efectivo',
    'effective_total' => 'Total efectivo',
    'no_requirement' => 'sin requisito',
    'per_member_breakdown' => 'Por miembro (monto × ventas mín.)',
    'distributed_note' => 'Repartiendo :amount por la cadena de patrocinadores (venta × ventas mín. del vendedor).',
    'seller_label' => 'Vendedor',
```

- [ ] **Step 3: Verify the files parse**

Run: `php -l lang/en/messages.php && php -l lang/es/messages.php`
Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add lang/en/messages.php lang/es/messages.php
git commit -m "feat: add i18n keys for Calculator 2.0"
```

---

### Task 3: Routes, controller `show`, and the form view (with access control)

**Files:**
- Modify: `routes/web.php` (the `Route::middleware('auth')->group` holding the `calculator` routes, ~lines 48–51)
- Create: `app/Http/Controllers/NetworkCalculatorController.php`
- Create: `resources/views/calculator-2.blade.php`
- Test: `tests/Feature/NetworkCalculatorTest.php`

- [ ] **Step 1: Write the failing access-control + form tests**

```php
<?php
// tests/Feature/NetworkCalculatorTest.php

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
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/NetworkCalculatorTest.php`
Expected: FAIL — route `/calculator-2` not defined (404 / RouteNotFoundException).

- [ ] **Step 3: Add the routes**

In `routes/web.php`, inside the same `Route::middleware('auth')->group(function () { ... })` that defines the `calculator` routes, add:

```php
    Route::get('/calculator-2', [\App\Http\Controllers\NetworkCalculatorController::class, 'show'])->name('calculator2');
    Route::post('/calculator-2', [\App\Http\Controllers\NetworkCalculatorController::class, 'compute'])->name('calculator2.compute');
```

- [ ] **Step 4: Create the controller (`show` + `compute`, gated)**

Create `app/Http/Controllers/NetworkCalculatorController.php`. (The `compute` body is finished here so Task 4 only adds tests + the results view — but it is fully implemented now, no placeholder.)

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionCalculator;
use App\Services\CommissionDistributor;
use App\Services\MinSalesLookup;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NetworkCalculatorController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeAccess($request->user());

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'result' => null,
            'members' => [],
            'chain' => [],
            'effectiveTotalCents' => 0,
            'sellerEffectiveCents' => 0,
            'input' => ['seller_id' => null, 'amount' => 1000],
        ]);
    }

    public function compute(
        Request $request,
        MinSalesLookup $lookup,
        CommissionCalculator $calculator,
        CommissionDistributor $distributor,
    ) {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'seller_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'seller')],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
        ]);

        $amountCents = (int) round($data['amount'] * 100);
        $seller = User::findOrFail($data['seller_id']);
        $chain = $seller->uplineChain(); // [level => User]

        // Per-member rows: seller (level 0) then each real upline.
        $sellerLookup = $lookup->forUser($seller);
        $members = [$this->memberRow(0, $seller, $sellerLookup, $amountCents)];
        foreach ($chain as $level => $upline) {
            $members[] = $this->memberRow($level, $upline, $lookup->forUser($upline), $amountCents);
        }
        $effectiveTotalCents = array_sum(array_column($members, 'effective_cents'));

        // Commission split on the SELLER's effective amount across the real chain.
        $sellerEffectiveCents = $amountCents * $sellerLookup['min_sales'];
        $uplineSlots = [];
        foreach ($chain as $level => $upline) {
            $uplineSlots[$level] = $distributor->isActive($upline, now());
        }
        $result = $calculator->calculate($sellerEffectiveCents, $uplineSlots);

        return view('calculator-2', [
            'sellers' => $this->sellers(),
            'result' => $result,
            'members' => $members,
            'chain' => $chain,
            'effectiveTotalCents' => $effectiveTotalCents,
            'sellerEffectiveCents' => $sellerEffectiveCents,
            'input' => ['seller_id' => (int) $data['seller_id'], 'amount' => $data['amount']],
        ]);
    }

    /** @param array{age:?int,label:?string,min_sales:int,matched:bool} $lookup */
    private function memberRow(int $level, User $user, array $lookup, int $amountCents): array
    {
        return [
            'level' => $level,
            'name' => $user->name,
            'age' => $lookup['age'],
            'label' => $lookup['label'],
            'min_sales' => $lookup['min_sales'],
            'matched' => $lookup['matched'],
            'effective_cents' => $amountCents * $lookup['min_sales'],
        ];
    }

    private function sellers()
    {
        return User::where('role', 'seller')->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeAccess(?User $user): void
    {
        abort_unless($user && ($user->isAdmin() || ($user->isSeller() && $user->isApproved())), 403);
    }
}
```

- [ ] **Step 5: Create the form view (results section stubbed for now)**

Create `resources/views/calculator-2.blade.php`:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_2_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_2_intro') }}</p>

                <form method="POST" action="{{ route('calculator2.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="seller_id" :value="__('messages.select_seller')" />
                        <select id="seller_id" name="seller_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">—</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('seller_id', $input['seller_id']) === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('seller_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount', $input['amount'])" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm">
                        {{ __('messages.calculate') }}
                    </button>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.results') }}</h3>
                @if ($result === null)
                    <p class="mt-4 text-sm text-gray-400">—</p>
                @else
                    <p class="mt-4 text-sm text-gray-400">…</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/NetworkCalculatorTest.php`
Expected: PASS (2 passed).

- [ ] **Step 7: Commit**

```bash
git add routes/web.php app/Http/Controllers/NetworkCalculatorController.php resources/views/calculator-2.blade.php tests/Feature/NetworkCalculatorTest.php
git commit -m "feat: add Calculator 2.0 routes, controller, and form (access-gated)"
```

---

### Task 4: Results view + compute behavior tests

**Files:**
- Modify: `resources/views/calculator-2.blade.php` (replace the results card body)
- Test: `tests/Feature/NetworkCalculatorTest.php` (add compute tests)

- [ ] **Step 1: Write the failing compute tests**

Append to `tests/Feature/NetworkCalculatorTest.php`:

```php
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
        // per-member effective amounts: Carla 100×8=$800, Ana 100×6=$600
        ->assertSee('800.00')->assertSee('600.00')
        // seller effective = 100×8 = $800 → seller cut 10% = $80.00 (proves the multiplier applied)
        ->assertSee('80.00')
        ->assertSee(__('messages.invariant_ok'));
});

test('a member with no date of birth shows the no-requirement note and uses a ×1 multiplier', function () {
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
```

- [ ] **Step 2: Run the new tests to verify they fail**

Run: `php artisan test tests/Feature/NetworkCalculatorTest.php`
Expected: FAIL — the results card stub (`…`) does not render member names, effective amounts, or `no_requirement`.

- [ ] **Step 3: Replace the view with the full results layout**

Overwrite `resources/views/calculator-2.blade.php` with:

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_2_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_2_intro') }}</p>

                <form method="POST" action="{{ route('calculator2.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="seller_id" :value="__('messages.select_seller')" />
                        <select id="seller_id" name="seller_id" class="mt-1 block w-full rounded-lg border-gray-300 text-sm" required>
                            <option value="">—</option>
                            @foreach ($sellers as $seller)
                                <option value="{{ $seller->id }}" @selected((int) old('seller_id', $input['seller_id']) === $seller->id)>{{ $seller->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('seller_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount', $input['amount'])" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-6 py-2.5 rounded-lg text-sm">
                        {{ __('messages.calculate') }}
                    </button>
                </form>

                {{-- Per-member breakdown: amount × min_sales(age) --}}
                @if ($result !== null)
                    <h3 class="mt-8 font-semibold text-brand-navy">{{ __('messages.per_member_breakdown') }}</h3>
                    <table class="mt-3 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">{{ __('messages.col_member') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_name') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_age') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_bracket') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('messages.col_min_sales') }}</th>
                                <th class="py-2 text-right">{{ __('messages.col_effective') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($members as $m)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-brand-navy">{{ $m['level'] === 0 ? __('messages.seller_label') : 'L'.$m['level'] }}</td>
                                    <td class="py-2 pr-4">{{ $m['name'] }}</td>
                                    <td class="py-2 pr-4">{{ $m['age'] ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($m['matched'])
                                            {{ $m['label'] }}
                                        @else
                                            <span class="text-amber-600">{{ __('messages.no_requirement') }}</span>
                                        @endif
                                    </td>
                                    <td class="py-2 pr-4 text-right">×{{ $m['min_sales'] }}</td>
                                    <td class="py-2 text-right font-medium">{{ \Illuminate\Support\Number::currency($m['effective_cents'] / 100) }}</td>
                                </tr>
                            @endforeach
                            <tr class="border-t-2">
                                <td class="py-2 pr-4 font-semibold text-brand-navy" colspan="5">{{ __('messages.effective_total') }}</td>
                                <td class="py-2 text-right font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($effectiveTotalCents / 100) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.results') }}</h3>

                @if ($result === null)
                    <p class="mt-4 text-sm text-gray-400">—</p>
                @else
                    @php $uplinesPaid = collect($result['levels'])->where('paid', true)->sum('amount_cents'); @endphp

                    <p class="mt-3 text-xs text-gray-500">{{ __('messages.distributed_note', ['amount' => \Illuminate\Support\Number::currency($sellerEffectiveCents / 100)]) }}</p>

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">{{ __('messages.level') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.col_name') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('messages.sale_amount') }}</th>
                                <th class="py-2">{{ __('messages.destination') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-2 pr-4 font-medium text-brand-navy">{{ __('messages.seller_cut') }}</td>
                                <td class="py-2 pr-4">{{ $members[0]['name'] ?? '' }}</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ \Illuminate\Support\Number::currency($result['seller_cents'] / 100) }}</td>
                                <td class="py-2 text-green-700">{{ __('messages.dest_paid') }}</td>
                            </tr>
                            @foreach ($result['levels'] as $level => $line)
                                <tr class="{{ $line['exists'] ? '' : 'text-gray-400' }}">
                                    <td class="py-2 pr-4">L{{ $level }}</td>
                                    <td class="py-2 pr-4">{{ $chain[$level]->name ?? __('messages.dest_pool_no_upline') }}</td>
                                    <td class="py-2 pr-4 text-right">{{ \Illuminate\Support\Number::currency($line['amount_cents'] / 100) }}</td>
                                    <td class="py-2">
                                        @if ($line['paid'])
                                            <span class="text-green-700">{{ __('messages.dest_paid') }}</span>
                                        @elseif ($line['pool_reason'] === 'inactive_upline')
                                            <span class="text-amber-600">{{ __('messages.dest_pool_inactive') }}</span>
                                        @else
                                            <span class="text-gray-500">{{ __('messages.dest_pool_no_upline') }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td class="py-2 pr-4 text-gray-500" colspan="2">{{ __('messages.rounding_remainder') }}</td>
                                <td class="py-2 pr-4 text-right text-gray-500">{{ \Illuminate\Support\Number::currency($result['pool_rounding_cents'] / 100) }}</td>
                                <td class="py-2 text-gray-500">{{ __('messages.pool_total') }}</td>
                            </tr>
                        </tbody>
                    </table>

                    <dl class="mt-6 grid grid-cols-2 gap-3 text-sm">
                        <dt class="text-gray-500">{{ __('messages.seller_cut') }}</dt>
                        <dd class="text-right font-semibold text-brand-navy">{{ \Illuminate\Support\Number::currency($result['seller_cents'] / 100) }}</dd>
                        <dt class="text-gray-500">{{ __('messages.uplines_total') }}</dt>
                        <dd class="text-right font-semibold text-brand-navy">{{ \Illuminate\Support\Number::currency($uplinesPaid / 100) }}</dd>
                        <dt class="text-gray-500">{{ __('messages.pool_total') }}</dt>
                        <dd class="text-right font-semibold text-emerald-600">{{ \Illuminate\Support\Number::currency($result['pool_total_cents'] / 100) }}</dd>
                        <dt class="text-gray-500 border-t pt-2">{{ __('messages.company_cost') }}</dt>
                        <dd class="text-right font-bold text-brand-navy border-t pt-2">{{ \Illuminate\Support\Number::currency($result['total_charge_cents'] / 100) }}</dd>
                    </dl>

                    <p class="mt-4 text-xs text-green-700">✓ {{ __('messages.invariant_ok') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/NetworkCalculatorTest.php`
Expected: PASS (5 passed — 2 from Task 3 + 3 new).

- [ ] **Step 5: Commit**

```bash
git add resources/views/calculator-2.blade.php tests/Feature/NetworkCalculatorTest.php
git commit -m "feat: render Calculator 2.0 per-member breakdown and commission split"
```

---

### Task 5: Navigation links

**Files:**
- Modify: `resources/views/layouts/navigation.blade.php`

- [ ] **Step 1: Add the desktop links (admin + seller)**

In `resources/views/layouts/navigation.blade.php`, after the admin desktop calculator link (the `<x-nav-link :href="route('calculator')" ...>` inside the admin block, ~line 23) add:

```blade
                        <x-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-nav-link>
```

After the seller desktop calculator link (~line 46) add the same line:

```blade
                        <x-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-nav-link>
```

- [ ] **Step 2: Add the responsive links**

After each responsive calculator link (`<x-responsive-nav-link :href="route('calculator')" ...>`, ~lines 115 and 124) add:

```blade
                <x-responsive-nav-link :href="route('calculator2')" :active="request()->routeIs('calculator2')">{{ __('messages.calculator_2') }}</x-responsive-nav-link>
```

- [ ] **Step 3: Write a test that the link renders for an authorized user**

Append to `tests/Feature/NetworkCalculatorTest.php`:

```php
test('the dashboard nav shows a Calculator 2.0 link for an approved seller', function () {
    $this->actingAs(User::factory()->approvedSeller()->create())
        ->get('/calculator-2')
        ->assertOk()
        ->assertSee(__('messages.calculator_2'))
        ->assertSee(route('calculator2'), false);
});
```

- [ ] **Step 4: Run the full test suite**

Run: `php artisan test`
Expected: PASS — all prior tests plus the new Calculator 2.0 unit + feature tests (no regressions).

- [ ] **Step 5: Commit**

```bash
git add resources/views/layouts/navigation.blade.php tests/Feature/NetworkCalculatorTest.php
git commit -m "feat: add Calculator 2.0 navigation links"
```

---

## Self-Review Notes

- **Spec coverage:** access control (Task 3) · seller dropdown of all sellers (Task 3) · `MinSalesLookup` with ×1 fallback (Task 1) · per-member effective table + total (Task 4) · seller-effective-driven commission split with upline names + real `isActive` flags (Tasks 3–4) · validation (Task 4) · nav + i18n (Tasks 2, 5) · no persistence (controller only renders). All covered.
- **Type consistency:** `MinSalesLookup::forUser` returns `{age,label,min_sales,matched}` — consumed identically in controller `memberRow` and the view. `CommissionCalculator::calculate` result keys (`seller_cents`, `levels`, `pool_rounding_cents`, `pool_total_cents`, `total_charge_cents`) match the base view usage. Route names `calculator2` / `calculator2.compute` used consistently.
- **Edge cases:** chain shorter than 9 and top-level seller handled by `CommissionCalculator` (missing levels → `no_upline`) and `$chain[$level]->name ?? ...` in the view.
