# Configuration → Min Sales Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an admin Configuration menu with a "Min sales" page that edits the minimum sales required per age bracket, backed by a new `min_sales_requirements` table (`min_age`/`max_age`/`min_sales`) seeded with 7 brackets (18–29 … 80+).

**Architecture:** A `min_sales_requirements` table with one row per non-overlapping age bracket; a `MinSalesRequirement` model with a `forAge()` query scope (for the future compliance check) and a `label()` helper. An admin-only Configuration dropdown (Breeze `x-dropdown`) links to a single-form editor that updates every bracket's `min_sales`. Seeded idempotently.

**Tech Stack:** Laravel 11, Breeze (Blade + Tailwind + Alpine), Pest on in-memory SQLite, MariaDB live.

**Branch:** create `build/config-min-sales` off `main` before Task 1.

---

## File Structure

**Created:**
- `database/migrations/xxxx_create_min_sales_requirements_table.php`
- `app/Models/MinSalesRequirement.php`
- `database/seeders/MinSalesRequirementSeeder.php`
- `app/Http/Controllers/Admin/AdminMinSalesController.php`
- `resources/views/admin/configuration/min-sales.blade.php`
- `tests/Feature/MinSalesRequirementTest.php`
- `tests/Feature/AdminMinSalesTest.php`

**Modified:**
- `database/seeders/DatabaseSeeder.php` — call the new seeder
- `routes/web.php` — two routes in the admin group
- `resources/views/layouts/navigation.blade.php` — Configuration dropdown (desktop + responsive)
- `lang/en/messages.php`, `lang/es/messages.php` — new keys

---

## Task 0: Branch

- [ ] From a clean `main`: `git checkout -b build/config-min-sales`

---

## Task 1: Table, model, seeder

**Files:**
- Create: `database/migrations/xxxx_create_min_sales_requirements_table.php`, `app/Models/MinSalesRequirement.php`, `database/seeders/MinSalesRequirementSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/MinSalesRequirementTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/MinSalesRequirementTest.php`:
```php
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
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=MinSalesRequirementTest`
Expected: FAIL — model/table/seeder not found.

- [ ] **Step 3: Create the migration**

Run `php artisan make:migration create_min_sales_requirements_table`, then set contents to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('min_sales_requirements', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('min_age');
            $table->unsignedTinyInteger('max_age')->nullable();
            $table->unsignedInteger('min_sales')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('min_sales_requirements');
    }
};
```

- [ ] **Step 4: Create the model**

Create `app/Models/MinSalesRequirement.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MinSalesRequirement extends Model
{
    protected $fillable = ['min_age', 'max_age', 'min_sales'];

    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'min_sales' => 'integer',
        ];
    }

    /**
     * The bracket that contains the given age. Brackets are non-overlapping,
     * so this matches at most one row. A null max_age means "no upper limit".
     */
    public function scopeForAge(Builder $query, int $age): Builder
    {
        return $query->where('min_age', '<=', $age)
            ->where(function (Builder $q) use ($age) {
                $q->whereNull('max_age')->orWhere('max_age', '>=', $age);
            });
    }

    public function label(): string
    {
        return $this->max_age === null
            ? "{$this->min_age}+"
            : "{$this->min_age}–{$this->max_age}"; // en dash
    }
}
```

- [ ] **Step 5: Create the seeder**

Create `database/seeders/MinSalesRequirementSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\MinSalesRequirement;
use Illuminate\Database\Seeder;

class MinSalesRequirementSeeder extends Seeder
{
    public function run(): void
    {
        if (MinSalesRequirement::count() > 0) {
            return; // idempotent — ranges are fixed, values are edited in the UI
        }

        $brackets = [
            ['min_age' => 18, 'max_age' => 29, 'min_sales' => 10],
            ['min_age' => 30, 'max_age' => 39, 'min_sales' => 8],
            ['min_age' => 40, 'max_age' => 49, 'min_sales' => 6],
            ['min_age' => 50, 'max_age' => 59, 'min_sales' => 5],
            ['min_age' => 60, 'max_age' => 69, 'min_sales' => 4],
            ['min_age' => 70, 'max_age' => 79, 'min_sales' => 3],
            ['min_age' => 80, 'max_age' => null, 'min_sales' => 2],
        ];

        foreach ($brackets as $bracket) {
            MinSalesRequirement::create($bracket);
        }
    }
}
```

- [ ] **Step 6: Register the seeder in `DatabaseSeeder`**

In `database/seeders/DatabaseSeeder.php`, at the END of the `run()` method (after the existing `$this->call(CommissionDemoSeeder::class);` line), add:
```php
$this->call(MinSalesRequirementSeeder::class);
```
Ensure the seeder is invoked; do not remove existing calls.

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=MinSalesRequirementTest` then full suite `php artisan test`.
Expected: the four new tests PASS; full suite stays green.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(config): min_sales_requirements table, model (forAge/label) and seeder"
```

---

## Task 2: i18n keys

**Files:**
- Modify: `lang/en/messages.php`, `lang/es/messages.php`
- Test: existing `tests/Feature/LocaleParityTest.php`

- [ ] **Step 1: Add the English keys**

Append to the array in `lang/en/messages.php` (before the closing `];`):
```php
    // Configuration / min sales
    'configuration' => 'Configuration',
    'min_sales_nav' => 'Min sales',
    'min_sales_title' => 'Minimum sales by age',
    'min_sales_intro' => 'Set the minimum number of sales required for each age bracket.',
    'age_range' => 'Age range',
    'minimum_sales' => 'Minimum sales',
    'min_sales_updated' => 'Minimum sales updated.',
```

- [ ] **Step 2: Add the Spanish keys**

Append to the array in `lang/es/messages.php` (before the closing `];`):
```php
    // Configuración / ventas mínimas
    'configuration' => 'Configuración',
    'min_sales_nav' => 'Ventas mínimas',
    'min_sales_title' => 'Ventas mínimas por edad',
    'min_sales_intro' => 'Define el número mínimo de ventas requerido para cada rango de edad.',
    'age_range' => 'Rango de edad',
    'minimum_sales' => 'Ventas mínimas',
    'min_sales_updated' => 'Ventas mínimas actualizadas.',
```

- [ ] **Step 3: Run the parity test**

Run: `php artisan test --filter=LocaleParityTest` then full suite.
Expected: PASS (en/es key sets identical).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat(config): EN/ES keys for Configuration and Min sales"
```

---

## Task 3: Controller, routes, page, navigation

**Files:**
- Create: `app/Http/Controllers/Admin/AdminMinSalesController.php`, `resources/views/admin/configuration/min-sales.blade.php`
- Modify: `routes/web.php`, `resources/views/layouts/navigation.blade.php`
- Test: `tests/Feature/AdminMinSalesTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AdminMinSalesTest.php`:
```php
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
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminMinSalesTest`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Admin/AdminMinSalesController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MinSalesRequirement;
use Illuminate\Http\Request;

class AdminMinSalesController extends Controller
{
    public function index()
    {
        $requirements = MinSalesRequirement::orderBy('min_age')->get();

        return view('admin.configuration.min-sales', compact('requirements'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'min_sales' => ['required', 'array'],
            'min_sales.*' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($data['min_sales'] as $id => $value) {
            MinSalesRequirement::where('id', $id)->update(['min_sales' => (int) $value]);
        }

        return redirect()->route('admin.configuration.min-sales')
            ->with('status', __('messages.min_sales_updated'));
    }
}
```

- [ ] **Step 4: Create the view**

Create `resources/views/admin/configuration/min-sales.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.min_sales_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.min_sales_intro') }}</p>

                <form method="POST" action="{{ route('admin.configuration.min-sales.update') }}" class="mt-6">
                    @csrf @method('PATCH')

                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-left text-gray-500">
                            <tr>
                                <th class="px-4 py-3">{{ __('messages.age_range') }}</th>
                                <th class="px-4 py-3">{{ __('messages.minimum_sales') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @foreach ($requirements as $requirement)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-brand-navy">{{ $requirement->label() }}</td>
                                    <td class="px-4 py-3">
                                        <input type="number" min="0" step="1"
                                               name="min_sales[{{ $requirement->id }}]"
                                               value="{{ old('min_sales.'.$requirement->id, $requirement->min_sales) }}"
                                               class="w-32 rounded-lg border-gray-300 text-sm" required>
                                        <x-input-error :messages="$errors->get('min_sales.'.$requirement->id)" class="mt-1" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="mt-6">
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.save') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add the routes**

In `routes/web.php`, inside the `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(...)` block, add:
```php
Route::get('/configuration/min-sales', [\App\Http\Controllers\Admin\AdminMinSalesController::class, 'index'])->name('configuration.min-sales');
Route::patch('/configuration/min-sales', [\App\Http\Controllers\Admin\AdminMinSalesController::class, 'update'])->name('configuration.min-sales.update');
```

- [ ] **Step 6: Add the Configuration dropdown to the desktop nav**

In `resources/views/layouts/navigation.blade.php`, inside the admin `@if (Auth::user()->isAdmin())` block of the DESKTOP links container (the `<div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">` — right after the admin `calculator` `<x-nav-link>`), insert:
```blade
<div class="hidden sm:flex sm:items-center">
    <x-dropdown align="left" width="48">
        <x-slot name="trigger">
            <button class="inline-flex items-center h-full px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out focus:outline-none
                {{ request()->routeIs('admin.configuration.*') ? 'border-brand-blue text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }}">
                <div>{{ __('messages.configuration') }}</div>
                <div class="ms-1">
                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </button>
        </x-slot>
        <x-slot name="content">
            <x-dropdown-link :href="route('admin.configuration.min-sales')">
                {{ __('messages.min_sales_nav') }}
            </x-dropdown-link>
        </x-slot>
    </x-dropdown>
</div>
```

- [ ] **Step 7: Add a Configuration section to the responsive nav**

In `resources/views/layouts/navigation.blade.php`, inside the admin `@if (Auth::user()->isAdmin())` block of the RESPONSIVE menu (the `<div class="pt-2 pb-3 space-y-1">` area — right after the admin `calculator` `<x-responsive-nav-link>`), insert:
```blade
<div class="px-4 pt-2 text-xs font-semibold uppercase text-gray-400">{{ __('messages.configuration') }}</div>
<x-responsive-nav-link :href="route('admin.configuration.min-sales')" :active="request()->routeIs('admin.configuration.min-sales')">
    {{ __('messages.min_sales_nav') }}
</x-responsive-nav-link>
```

- [ ] **Step 8: Run tests**

Run: `php artisan test --filter=AdminMinSalesTest` then full suite.
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat(config): Configuration menu and editable Min sales page"
```

---

## Task 4: Build + final verification

- [ ] **Step 1: Build assets and run the full suite**

Run:
```bash
npm run build && php artisan test
```
Expected: assets compile cleanly; ALL tests pass.

- [ ] **Step 2: Commit if assets changed**

```bash
git add -A
git commit -m "chore: rebuild assets for Configuration min-sales" || echo "nothing to commit"
```

---

## Final Verification

- [ ] `php artisan test` → all green.
- [ ] `npm run build` → no errors.
- [ ] **Live DB (MariaDB):** `php artisan migrate --force` then
  `php artisan db:seed --class=MinSalesRequirementSeeder --force` (NEVER
  `migrate:fresh` — the live DB has real accounts).
- [ ] Manual walkthrough on `php artisan serve` as `admin@admin.com` / `admin`:
  1. The top nav shows a **Configuration** dropdown → **Min sales**.
  2. The page lists all 7 brackets (18–29 … 80+) with editable numbers.
  3. Change a value, Save → success flash, value persists on reload.
  4. Enter a negative number → validation error, nothing saved.
  5. Toggle ES → labels localize (Configuración, Ventas mínimas por edad, Rango de edad).
  6. Log in as a seller → no Configuration menu; visiting `/admin/configuration/min-sales` is 403.
