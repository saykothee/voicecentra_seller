# VoiceCentra Sellers Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a Laravel + Breeze sellers portal for VoiceCentra (a voice AI phone assistant) with a Bold-Navy marketing landing page, seller self-registration gated by admin approval, separate seller/admin dashboards, and an EN/ES bilingual UI.

**Architecture:** Laravel 11 with Laravel Breeze (Blade + Tailwind) for server-rendered auth and UI. Two user types (`seller`, `admin`) and a seller `status` (`pending`/`approved`/`rejected`) live as columns on the `users` table. Route middleware enforces admin-only areas and the seller approval gate. A `SetLocale` middleware + session locale drives EN/ES localization. Tests run on in-memory SQLite; production runs on the user's existing MySQL server.

**Tech Stack:** Laravel 11, PHP 8.3, Laravel Breeze (Blade), Tailwind CSS, Pest, MySQL (prod) / SQLite in-memory (tests).

---

## File Structure

**Created:**
- `app/Http/Controllers/DashboardController.php` — role/status-aware redirect
- `app/Http/Controllers/PendingController.php` — pending/rejected holding screen
- `app/Http/Controllers/SellerDashboardController.php` — seller dashboard
- `app/Http/Controllers/Admin/AdminDashboardController.php` — admin stat cards
- `app/Http/Controllers/Admin/AdminSellerController.php` — sellers list + approve/reject
- `app/Http/Controllers/LocaleController.php` — EN/ES switch
- `app/Http/Middleware/EnsureUserIsAdmin.php`
- `app/Http/Middleware/EnsureSellerApproved.php`
- `app/Http/Middleware/SetLocale.php`
- `database/migrations/xxxx_add_role_and_status_to_users_table.php`
- `resources/views/landing.blade.php` — Bold-Navy marketing page
- `resources/views/components/public-layout.blade.php` — public/marketing layout (nav + footer), resolved as `<x-public-layout>`
- `resources/views/pending.blade.php`
- `resources/views/seller/dashboard.blade.php`
- `resources/views/admin/dashboard.blade.php`
- `resources/views/admin/sellers/index.blade.php`
- `lang/en/messages.php`, `lang/es/messages.php`, `lang/es/auth.php`, `lang/es/validation.php`
- `public/images/voicecentra_icon.svg`, `voicecentra_wordmark.svg`, `voicecentra_icon_white.svg`
- `tests/Feature/SellerApprovalTest.php`, `tests/Feature/LocaleTest.php`, `tests/Feature/LandingTest.php`

**Modified:**
- `.env` — MySQL connection + admin seed credentials
- `bootstrap/app.php` — register middleware aliases + append SetLocale to web group
- `app/Models/User.php` — fillable, helpers
- `database/factories/UserFactory.php` — defaults + states
- `database/seeders/DatabaseSeeder.php` — admin + demo sellers
- `routes/web.php` — landing, dashboard redirect, seller/admin/locale routes
- `app/Http/Controllers/Auth/RegisteredUserController.php` — phone field
- `resources/views/auth/register.blade.php` — phone input
- `resources/views/layouts/navigation.blade.php` — brand + language switcher
- `resources/views/layouts/guest.blade.php` — navy branded auth shell
- `tailwind.config.js` — brand colors
- `tests/Feature/Auth/RegistrationTest.php` — include phone field

---

## Task 1: Scaffold Laravel + Breeze and configure the database

**Files:**
- Create: entire Laravel skeleton in the project root (preserving existing `.git`, `docs/`, `brand-assets/`, `.superpowers/`)
- Modify: `.env`

> The project root already contains `.git`, `.gitignore`, `brand-assets/`, `docs/`, and `.superpowers/`. `composer create-project` refuses a non-empty directory, so we scaffold into a temp dir and copy in, excluding `.git`.

- [ ] **Step 1: Scaffold Laravel 11 into a temp directory**

Run:
```bash
cd /Users/cesar/Documents/proyectos/sellers_portal
composer create-project laravel/laravel _scaffold "11.*" --no-interaction
```
Expected: `_scaffold/` created with a full Laravel 11 app.

- [ ] **Step 2: Copy scaffold into the project root, then remove temp dir**

Run:
```bash
rsync -a --exclude='.git' _scaffold/ ./
rm -rf _scaffold
printf '/.superpowers/\n' >> .gitignore
```
Expected: `artisan`, `composer.json`, `app/`, `routes/`, etc. now in the project root. `.gitignore` (Laravel's, which ignores `/vendor`, `/node_modules`, `.env`) now also ignores `/.superpowers/`.

- [ ] **Step 3: Install Laravel Breeze (Blade stack, Pest tests)**

Run:
```bash
composer require laravel/breeze --dev --no-interaction
php artisan breeze:install blade --pest
```
Expected: Breeze publishes auth controllers/views/routes, switches the test suite to Pest, and updates `package.json`.

- [ ] **Step 4: Install and build front-end assets**

Run:
```bash
npm install && npm run build
```
Expected: `public/build/` produced, no errors.

- [ ] **Step 5: Configure `.env` for the user's MySQL server**

Edit `.env` — set these keys (the user supplies the real values; ask for host/port/db/user/password before running migrations):
```dotenv
APP_NAME="VoiceCentra Sellers"
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=voicecentra_sellers
DB_USERNAME=__FILL_IN__
DB_PASSWORD=__FILL_IN__

ADMIN_EMAIL=admin@voicecentra.com
ADMIN_PASSWORD=ChangeMe123!
```

- [ ] **Step 6: Verify the database connection and base migration**

Run:
```bash
php artisan migrate --force
```
Expected: PASS — Breeze/Laravel base tables (`users`, `sessions`, `cache`, `jobs`) created. If it errors with "Access denied" / "Unknown database", stop and fix the `.env` credentials (or create the database) before continuing.

- [ ] **Step 7: Run the test suite (baseline green)**

Run:
```bash
php artisan test
```
Expected: PASS — Breeze's bundled auth tests pass on in-memory SQLite. (`phpunit.xml` ships with `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`.)

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "chore: scaffold Laravel 11 + Breeze (Blade), configure MySQL"
```

---

## Task 2: Add role & status to the User model (migration, model, factory)

**Files:**
- Create: `database/migrations/xxxx_add_role_and_status_to_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Test: `tests/Feature/SellerApprovalTest.php`

- [ ] **Step 1: Write a failing test for the User helper methods**

Create `tests/Feature/SellerApprovalTest.php`:
```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="role and status helpers"`
Expected: FAIL — `Call to undefined method ... admin()` (factory state missing) / undefined `isAdmin()`.

- [ ] **Step 3: Create the migration**

Run:
```bash
php artisan make:migration add_role_and_status_to_users_table
```
Then set its contents to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['seller', 'admin'])->default('seller')->after('email');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending')->after('role');
            $table->string('phone')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('phone');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
            $table->index(['role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropColumn(['role', 'status', 'phone', 'approved_at', 'approved_by']);
        });
    }
};
```

- [ ] **Step 4: Add helpers and fillable fields to the User model**

In `app/Models/User.php`, set `$fillable` and add helper methods:
```php
protected $fillable = [
    'name',
    'email',
    'password',
    'phone',
];

public function isAdmin(): bool
{
    return $this->role === 'admin';
}

public function isSeller(): bool
{
    return $this->role === 'seller';
}

public function isApproved(): bool
{
    return $this->status === 'approved';
}
```

Also add an `approved_at` datetime cast to the `casts()` method:
```php
'approved_at' => 'datetime',
```

> **Security note:** `role`, `status`, `approved_at`, and `approved_by` are deliberately kept OUT of `$fillable` (least privilege — they must never be mass-assignable from request input). Factory `create()` sets them directly (bypasses guarding), and Task 7 sets them via explicit attribute assignment + `save()`, so they never need to be fillable.

- [ ] **Step 5: Add factory defaults and states**

In `database/factories/UserFactory.php`, set the default `definition()` to include role/status and add states:
```php
public function definition(): array
{
    return [
        'name' => fake()->name(),
        'email' => fake()->unique()->safeEmail(),
        'email_verified_at' => now(),
        'password' => static::$password ??= Hash::make('password'),
        'remember_token' => Str::random(10),
        'role' => 'seller',
        'status' => 'pending',
        'phone' => fake()->numerify('555-####'),
    ];
}

public function admin(): static
{
    return $this->state(fn () => ['role' => 'admin', 'status' => 'approved']);
}

public function pending(): static
{
    return $this->state(fn () => ['role' => 'seller', 'status' => 'pending']);
}

public function approvedSeller(): static
{
    return $this->state(fn () => [
        'role' => 'seller',
        'status' => 'approved',
        'approved_at' => now(),
    ]);
}
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --filter="role and status helpers"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: add role and status to users with model helpers and factory states"
```

---

## Task 3: Add the three middleware and register them

**Files:**
- Create: `app/Http/Middleware/EnsureUserIsAdmin.php`, `EnsureSellerApproved.php`, `SetLocale.php`
- Modify: `bootstrap/app.php`

> No failing test in this task — the middleware are exercised by Tasks 5–7. This task wires them up.

- [ ] **Step 1: Create `EnsureUserIsAdmin` middleware**

Create `app/Http/Middleware/EnsureUserIsAdmin.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() || ! $request->user()->isAdmin()) {
            abort(403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Create `EnsureSellerApproved` middleware**

Create `app/Http/Middleware/EnsureSellerApproved.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSellerApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isSeller() && ! $user->isApproved()) {
            return redirect()->route('pending');
        }

        return $next($request);
    }
}
```

- [ ] **Step 3: Create `SetLocale` middleware**

Create `app/Http/Middleware/SetLocale.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', config('app.locale'));

        if (in_array($locale, ['en', 'es'], true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Register middleware in `bootstrap/app.php`**

In `bootstrap/app.php`, update the `->withMiddleware(...)` closure to:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->web(append: [
        \App\Http\Middleware\SetLocale::class,
    ]);

    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
        'seller.approved' => \App\Http\Middleware\EnsureSellerApproved::class,
    ]);
})
```
Ensure `use Illuminate\Foundation\Configuration\Middleware;` is present at the top (it is, by default).

- [ ] **Step 5: Verify the app still boots**

Run: `php artisan route:list`
Expected: route table prints with no errors.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add admin, seller-approval, and locale middleware"
```

---

## Task 4: Registration captures phone and creates pending sellers

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/views/auth/register.blade.php`, `tests/Feature/Auth/RegistrationTest.php`
- Test: `tests/Feature/SellerApprovalTest.php`

- [ ] **Step 1: Write a failing test for pending-seller registration**

Append to `tests/Feature/SellerApprovalTest.php`:
```php
test('new registrations become pending sellers', function () {
    $response = $this->post('/register', [
        'name' => 'Jane Seller',
        'email' => 'jane@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('seller');
    expect($user->status)->toBe('pending');
    expect($user->phone)->toBe('555-0100');
    $this->assertAuthenticated();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter="become pending sellers"`
Expected: FAIL — phone is not stored / validation rejects unknown field.

- [ ] **Step 3: Add phone validation and persistence in the controller**

In `app/Http/Controllers/Auth/RegisteredUserController.php`, update the `store()` method's validation and `User::create`:
```php
$request->validate([
    'name' => ['required', 'string', 'max:255'],
    'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
    'phone' => ['required', 'string', 'max:30'],
    'password' => ['required', 'confirmed', Rules\Password::defaults()],
]);

$user = User::create([
    'name' => $request->name,
    'email' => $request->email,
    'phone' => $request->phone,
    'password' => Hash::make($request->password),
]);
```
(`role`/`status` default to `seller`/`pending` at the DB level, so they need not be set here.)

- [ ] **Step 4: Add the phone field to the register form**

In `resources/views/auth/register.blade.php`, add a phone block after the email block:
```blade
<!-- Phone -->
<div class="mt-4">
    <x-input-label for="phone" :value="__('messages.phone')" />
    <x-text-input id="phone" class="block mt-1 w-full" type="text" name="phone"
                  :value="old('phone')" required autocomplete="tel" />
    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
</div>
```

- [ ] **Step 5: Update Breeze's bundled registration test to send phone**

In `tests/Feature/Auth/RegistrationTest.php`, in the "new users can register" test, add `'phone' => '555-0100',` to the `$this->post('/register', [...])` payload so it stays green.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="Registration"`
Expected: PASS (both the new pending-seller test and Breeze's registration test).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: capture phone on registration and create pending sellers"
```

---

## Task 5: Dashboard redirect + pending holding page

**Files:**
- Create: `app/Http/Controllers/DashboardController.php`, `app/Http/Controllers/PendingController.php`, `resources/views/pending.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SellerApprovalTest.php`

- [ ] **Step 1: Write failing tests for the redirect and gate**

Append to `tests/Feature/SellerApprovalTest.php`:
```php
test('dashboard redirects admins to the admin dashboard', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('admin.dashboard'));
});

test('dashboard redirects approved sellers to the seller dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect(route('seller.dashboard'));
});

test('dashboard redirects pending sellers to the pending page', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/dashboard')->assertRedirect(route('pending'));
});

test('the pending page renders for an authenticated seller', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/pending')->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="dashboard redirects"`
Expected: FAIL — routes `admin.dashboard` / `seller.dashboard` / `pending` not defined.

- [ ] **Step 3: Create `DashboardController`**

Create `app/Http/Controllers/DashboardController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }

        if ($user->isApproved()) {
            return redirect()->route('seller.dashboard');
        }

        return redirect()->route('pending');
    }
}
```

- [ ] **Step 4: Create `PendingController`**

Create `app/Http/Controllers/PendingController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PendingController extends Controller
{
    public function show(Request $request)
    {
        return view('pending', ['user' => $request->user()]);
    }
}
```

- [ ] **Step 5: Create the pending view**

Create `resources/views/pending.blade.php`:
```blade
<x-guest-layout>
    <div class="text-center">
        @if ($user->status === 'rejected')
            <h1 class="text-2xl font-bold text-brand-navy">{{ __('messages.pending_rejected_title') }}</h1>
            <p class="mt-4 text-gray-600">{{ __('messages.pending_rejected_body') }}</p>
        @else
            <h1 class="text-2xl font-bold text-brand-navy">{{ __('messages.pending_title') }}</h1>
            <p class="mt-4 text-gray-600">{{ __('messages.pending_body') }}</p>
        @endif

        <form method="POST" action="{{ route('logout') }}" class="mt-8">
            @csrf
            <button type="submit" class="text-sm text-brand-blue underline">
                {{ __('messages.log_out') }}
            </button>
        </form>
    </div>
</x-guest-layout>
```

- [ ] **Step 6: Wire the routes**

In `routes/web.php`, replace Breeze's default `/dashboard` closure route with:
```php
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PendingController;

Route::get('/dashboard', DashboardController::class)
    ->middleware('auth')->name('dashboard');

Route::get('/pending', [PendingController::class, 'show'])
    ->middleware('auth')->name('pending');
```
(Remove the `'verified'` middleware that Breeze put on the dashboard route — this app does not use email verification.)

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test --filter="dashboard redirects|pending page renders"`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat: role-aware dashboard redirect and pending holding page"
```

---

## Task 6: Seller dashboard

**Files:**
- Create: `app/Http/Controllers/SellerDashboardController.php`, `resources/views/seller/dashboard.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SellerApprovalTest.php`

- [ ] **Step 1: Write failing tests for the seller dashboard gate**

Append to `tests/Feature/SellerApprovalTest.php`:
```php
test('approved sellers can view their dashboard', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertOk()->assertSee($seller->name);
});

test('pending sellers cannot view the seller dashboard', function () {
    $seller = User::factory()->pending()->create();
    $this->actingAs($seller)->get('/seller/dashboard')->assertRedirect(route('pending'));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="seller dashboard|sellers can view their dashboard"`
Expected: FAIL — route `seller.dashboard` not defined.

- [ ] **Step 3: Create `SellerDashboardController`**

Create `app/Http/Controllers/SellerDashboardController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SellerDashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        return view('seller.dashboard', ['user' => $request->user()]);
    }
}
```

- [ ] **Step 4: Create the seller dashboard view**

Create `resources/views/seller/dashboard.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">
            {{ __('messages.seller_dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-bold text-brand-navy">
                    {{ __('messages.welcome_name', ['name' => $user->name]) }}
                </h3>
                <p class="mt-2 text-gray-600">{{ __('messages.seller_welcome_body') }}</p>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <h4 class="font-semibold text-brand-navy">{{ __('messages.your_profile') }}</h4>
                    <dl class="mt-3 text-sm text-gray-600 space-y-1">
                        <div><dt class="inline font-medium">{{ __('messages.email') }}:</dt> <dd class="inline">{{ $user->email }}</dd></div>
                        <div><dt class="inline font-medium">{{ __('messages.phone') }}:</dt> <dd class="inline">{{ $user->phone }}</dd></div>
                    </dl>
                    <a href="{{ route('profile.edit') }}" class="mt-4 inline-block text-sm text-brand-blue underline">
                        {{ __('messages.edit_profile') }}
                    </a>
                </div>

                <div class="bg-brand-navy text-white shadow-sm sm:rounded-lg p-6 flex flex-col justify-center">
                    <h4 class="font-semibold">{{ __('messages.sales_tools') }}</h4>
                    <p class="mt-2 text-sm text-blue-100">{{ __('messages.sales_tools_soon') }}</p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add:
```php
use App\Http\Controllers\SellerDashboardController;

Route::middleware(['auth', 'seller.approved'])->group(function () {
    Route::get('/seller/dashboard', SellerDashboardController::class)->name('seller.dashboard');
});
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter="sellers can view their dashboard|pending sellers cannot"`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: seller dashboard with approval gate"
```

---

## Task 7: Admin dashboard, sellers table, approve/reject

**Files:**
- Create: `app/Http/Controllers/Admin/AdminDashboardController.php`, `app/Http/Controllers/Admin/AdminSellerController.php`, `resources/views/admin/dashboard.blade.php`, `resources/views/admin/sellers/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SellerApprovalTest.php`

- [ ] **Step 1: Write failing tests for admin access and approve/reject**

Append to `tests/Feature/SellerApprovalTest.php`:
```php
test('admins can view the admin dashboard and sellers list', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    $this->actingAs($admin)->get('/admin/sellers')->assertOk();
});

test('non-admins are forbidden from admin routes', function () {
    $seller = User::factory()->approvedSeller()->create();
    $this->actingAs($seller)->get('/admin/dashboard')->assertForbidden();
    $this->actingAs($seller)->get('/admin/sellers')->assertForbidden();
});

test('an admin can approve a pending seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.approve', $seller))
        ->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('approved');
    expect($seller->approved_by)->toBe($admin->id);
    expect($seller->approved_at)->not->toBeNull();
});

test('an admin can reject a pending seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.reject', $seller))
        ->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('rejected');
    expect($seller->approved_at)->toBeNull();
    expect($seller->approved_by)->toBeNull();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter="admin can|admins can view|non-admins are forbidden"`
Expected: FAIL — admin routes not defined.

- [ ] **Step 3: Create `AdminDashboardController`**

Create `app/Http/Controllers/Admin/AdminDashboardController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class AdminDashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total' => User::where('role', 'seller')->count(),
            'pending' => User::where('role', 'seller')->where('status', 'pending')->count(),
            'approved' => User::where('role', 'seller')->where('status', 'approved')->count(),
            'rejected' => User::where('role', 'seller')->where('status', 'rejected')->count(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
```

- [ ] **Step 4: Create `AdminSellerController`**

Create `app/Http/Controllers/Admin/AdminSellerController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class AdminSellerController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $sellers = User::where('role', 'seller')
            ->when(in_array($status, ['pending', 'approved', 'rejected'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sellers.index', compact('sellers', 'status'));
    }

    public function approve(User $user)
    {
        abort_unless($user->isSeller(), 404);

        // role/status/audit fields are intentionally not in $fillable (least
        // privilege), so set them via explicit assignment rather than update([]).
        $user->status = 'approved';
        $user->approved_at = now();
        $user->approved_by = auth()->id();
        $user->save();

        return back()->with('status', __('messages.seller_approved'));
    }

    public function reject(User $user)
    {
        abort_unless($user->isSeller(), 404);

        $user->status = 'rejected';
        $user->approved_at = null;
        $user->approved_by = null;
        $user->save();

        return back()->with('status', __('messages.seller_rejected'));
    }
}
```

- [ ] **Step 5: Create the admin dashboard view**

Create `resources/views/admin/dashboard.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">
            {{ __('messages.admin_dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-navy">{{ $stats['total'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.total_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-amber-500">{{ $stats['pending'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.pending_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-brand-blue">{{ $stats['approved'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.approved_sellers') }}</div>
                </div>
                <div class="bg-white shadow-sm sm:rounded-lg p-6">
                    <div class="text-3xl font-bold text-red-500">{{ $stats['rejected'] }}</div>
                    <div class="mt-1 text-sm text-gray-500">{{ __('messages.rejected_sellers') }}</div>
                </div>
            </div>

            <a href="{{ route('admin.sellers.index') }}"
               class="mt-8 inline-block bg-brand-blue text-white px-5 py-2.5 rounded-lg text-sm font-semibold">
                {{ __('messages.manage_sellers') }}
            </a>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Create the sellers table view**

Create `resources/views/admin/sellers/index.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">
            {{ __('messages.manage_sellers') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">
                    {{ session('status') }}
                </div>
            @endif

            <div class="mb-4 flex gap-2 text-sm">
                @foreach (['' => 'all', 'pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected'] as $value => $label)
                    <a href="{{ route('admin.sellers.index', $value ? ['status' => $value] : []) }}"
                       class="px-3 py-1.5 rounded-full {{ ($status ?? '') === $value ? 'bg-brand-blue text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ __('messages.filter_'.$label) }}
                    </a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.name') }}</th>
                            <th class="px-4 py-3">{{ __('messages.email') }}</th>
                            <th class="px-4 py-3">{{ __('messages.phone') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3">{{ __('messages.registered') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sellers as $seller)
                            <tr>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ $seller->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $seller->email }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $seller->phone }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-amber-100 text-amber-700' => $seller->status === 'pending',
                                            'bg-green-100 text-green-700' => $seller->status === 'approved',
                                            'bg-red-100 text-red-700' => $seller->status === 'rejected',
                                        ])">
                                        {{ __('messages.status_'.$seller->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $seller->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($seller->status !== 'approved')
                                            <form method="POST" action="{{ route('admin.sellers.approve', $seller) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-brand-blue font-medium">{{ __('messages.approve') }}</button>
                                            </form>
                                        @endif
                                        @if ($seller->status !== 'rejected')
                                            <form method="POST" action="{{ route('admin.sellers.reject', $seller) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-red-600 font-medium">{{ __('messages.reject') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_sellers') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sellers->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 7: Add the admin route group**

In `routes/web.php`, add:
```php
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminSellerController;

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index'])->name('dashboard');
    Route::get('/sellers', [AdminSellerController::class, 'index'])->name('sellers.index');
    Route::patch('/sellers/{user}/approve', [AdminSellerController::class, 'approve'])->name('sellers.approve');
    Route::patch('/sellers/{user}/reject', [AdminSellerController::class, 'reject'])->name('sellers.reject');
});
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test --filter="admin|forbidden"`
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: admin dashboard, sellers table, and approve/reject actions"
```

---

## Task 8: Localization — lang files, locale switch, language toggle

**Files:**
- Create: `app/Http/Controllers/LocaleController.php`, `lang/en/messages.php`, `lang/es/messages.php`, `lang/es/auth.php`, `lang/es/validation.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/LocaleTest.php`

- [ ] **Step 1: Publish Laravel's base language files**

Run:
```bash
php artisan lang:publish
```
Expected: `lang/en/` populated with `auth.php`, `validation.php`, `pagination.php`, `passwords.php`.

- [ ] **Step 2: Write a failing test for the locale switch**

Create `tests/Feature/LocaleTest.php`:
```php
<?php

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the locale can be switched to spanish and is stored in session', function () {
    $this->get('/lang/es')->assertRedirect();
    expect(session('locale'))->toBe('es');
});

test('an invalid locale is ignored', function () {
    $this->get('/lang/fr');
    expect(session('locale'))->toBeNull();
});

test('spanish landing shows translated hero headline', function () {
    session(['locale' => 'es']);
    $this->get('/')->assertSee(__('messages.hero_title', [], 'es'), false);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=LocaleTest`
Expected: FAIL — `/lang/es` route undefined.

- [ ] **Step 4: Create `LocaleController`**

Create `app/Http/Controllers/LocaleController.php`:
```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale)
    {
        if (in_array($locale, ['en', 'es'], true)) {
            session(['locale' => $locale]);
        }

        return redirect()->back();
    }
}
```

- [ ] **Step 5: Add the locale route**

In `routes/web.php`, add:
```php
use App\Http\Controllers\LocaleController;

Route::get('/lang/{locale}', LocaleController::class)->name('locale.switch');
```

- [ ] **Step 6: Create the English message catalog**

Create `lang/en/messages.php`:
```php
<?php

return [
    // Nav / shared
    'log_in' => 'Log in',
    'log_out' => 'Log out',
    'register' => 'Register',
    'become_seller' => 'Become a seller',
    'email' => 'Email',
    'phone' => 'Phone',
    'name' => 'Name',
    'status' => 'Status',
    'actions' => 'Actions',
    'registered' => 'Registered',
    'edit_profile' => 'Edit profile',

    // Landing
    'hero_eyebrow' => 'Earn by selling voice AI',
    'hero_title' => 'Sell the AI phone assistant that never misses a call.',
    'hero_subtitle' => 'Join the VoiceCentra seller team. Bring businesses a 24/7 voice AI receptionist — and earn commission on every deal.',
    'hero_cta_primary' => 'Become a seller',
    'hero_cta_secondary' => 'See how it works',
    'how_title' => 'How it works',
    'how_step1_title' => 'Sign up',
    'how_step1_body' => 'Create your seller account in minutes.',
    'how_step2_title' => 'Get approved',
    'how_step2_body' => 'Our team reviews and activates your account.',
    'how_step3_title' => 'Start selling & earning',
    'how_step3_body' => 'Bring VoiceCentra to businesses and earn on every deal.',
    'sell_title' => "What you'll sell",
    'sell_feature1_title' => 'Answers 24/7',
    'sell_feature1_body' => 'An AI receptionist that never sleeps and never misses a call.',
    'sell_feature2_title' => 'Books appointments',
    'sell_feature2_body' => 'Captures leads and schedules bookings automatically.',
    'sell_feature3_title' => 'Multilingual',
    'sell_feature3_body' => 'Speaks to customers in their own language.',
    'why_title' => 'Why sell with VoiceCentra',
    'why_feature1_title' => 'Commission on every deal',
    'why_feature1_body' => 'Competitive recurring commissions.',
    'why_feature2_title' => 'A growing market',
    'why_feature2_body' => 'Every business needs to answer the phone.',
    'why_feature3_title' => 'Materials & support',
    'why_feature3_body' => 'Sales decks, demos, and a team that backs you up.',
    'final_cta_title' => 'Ready to start earning?',
    'final_cta_body' => 'Join the VoiceCentra seller team today.',
    'footer_rights' => 'All rights reserved.',

    // Pending
    'pending_title' => 'Your application is under review',
    'pending_body' => "Thanks for signing up! An admin is reviewing your account. You'll get access as soon as you're approved.",
    'pending_rejected_title' => 'Your application was not approved',
    'pending_rejected_body' => 'Please contact the VoiceCentra team if you believe this was a mistake.',

    // Seller dashboard
    'seller_dashboard' => 'Seller Dashboard',
    'welcome_name' => 'Welcome, :name',
    'seller_welcome_body' => "You're all set. Your selling tools will appear here.",
    'your_profile' => 'Your profile',
    'sales_tools' => 'Sales tools',
    'sales_tools_soon' => 'Coming soon — decks, demos, and deal tracking.',

    // Admin
    'admin_dashboard' => 'Admin Dashboard',
    'total_sellers' => 'Total sellers',
    'pending_sellers' => 'Pending',
    'approved_sellers' => 'Approved',
    'rejected_sellers' => 'Rejected',
    'manage_sellers' => 'Manage sellers',
    'approve' => 'Approve',
    'reject' => 'Reject',
    'seller_approved' => 'Seller approved.',
    'seller_rejected' => 'Seller rejected.',
    'no_sellers' => 'No sellers found.',
    'filter_all' => 'All',
    'filter_pending' => 'Pending',
    'filter_approved' => 'Approved',
    'filter_rejected' => 'Rejected',
    'status_pending' => 'Pending',
    'status_approved' => 'Approved',
    'status_rejected' => 'Rejected',
];
```

- [ ] **Step 7: Create the Spanish message catalog**

Create `lang/es/messages.php`:
```php
<?php

return [
    'log_in' => 'Iniciar sesión',
    'log_out' => 'Cerrar sesión',
    'register' => 'Registrarse',
    'become_seller' => 'Conviértete en vendedor',
    'email' => 'Correo',
    'phone' => 'Teléfono',
    'name' => 'Nombre',
    'status' => 'Estado',
    'actions' => 'Acciones',
    'registered' => 'Registrado',
    'edit_profile' => 'Editar perfil',

    'hero_eyebrow' => 'Gana vendiendo IA de voz',
    'hero_title' => 'Vende el asistente telefónico con IA que nunca pierde una llamada.',
    'hero_subtitle' => 'Únete al equipo de vendedores de VoiceCentra. Ofrece a las empresas una recepcionista con IA disponible 24/7 y gana comisión en cada venta.',
    'hero_cta_primary' => 'Conviértete en vendedor',
    'hero_cta_secondary' => 'Cómo funciona',
    'how_title' => 'Cómo funciona',
    'how_step1_title' => 'Regístrate',
    'how_step1_body' => 'Crea tu cuenta de vendedor en minutos.',
    'how_step2_title' => 'Obtén aprobación',
    'how_step2_body' => 'Nuestro equipo revisa y activa tu cuenta.',
    'how_step3_title' => 'Empieza a vender y ganar',
    'how_step3_body' => 'Lleva VoiceCentra a las empresas y gana en cada venta.',
    'sell_title' => 'Qué venderás',
    'sell_feature1_title' => 'Responde 24/7',
    'sell_feature1_body' => 'Una recepcionista con IA que nunca duerme ni pierde una llamada.',
    'sell_feature2_title' => 'Agenda citas',
    'sell_feature2_body' => 'Captura clientes potenciales y agenda citas automáticamente.',
    'sell_feature3_title' => 'Multilingüe',
    'sell_feature3_body' => 'Habla con los clientes en su propio idioma.',
    'why_title' => 'Por qué vender con VoiceCentra',
    'why_feature1_title' => 'Comisión en cada venta',
    'why_feature1_body' => 'Comisiones recurrentes y competitivas.',
    'why_feature2_title' => 'Un mercado en crecimiento',
    'why_feature2_body' => 'Todas las empresas necesitan contestar el teléfono.',
    'why_feature3_title' => 'Materiales y soporte',
    'why_feature3_body' => 'Presentaciones, demos y un equipo que te respalda.',
    'final_cta_title' => '¿Listo para empezar a ganar?',
    'final_cta_body' => 'Únete hoy al equipo de vendedores de VoiceCentra.',
    'footer_rights' => 'Todos los derechos reservados.',

    'pending_title' => 'Tu solicitud está en revisión',
    'pending_body' => '¡Gracias por registrarte! Un administrador está revisando tu cuenta. Tendrás acceso en cuanto seas aprobado.',
    'pending_rejected_title' => 'Tu solicitud no fue aprobada',
    'pending_rejected_body' => 'Por favor contacta al equipo de VoiceCentra si crees que fue un error.',

    'seller_dashboard' => 'Panel del vendedor',
    'welcome_name' => 'Bienvenido, :name',
    'seller_welcome_body' => 'Todo listo. Tus herramientas de venta aparecerán aquí.',
    'your_profile' => 'Tu perfil',
    'sales_tools' => 'Herramientas de venta',
    'sales_tools_soon' => 'Próximamente: presentaciones, demos y seguimiento de ventas.',

    'admin_dashboard' => 'Panel de administración',
    'total_sellers' => 'Vendedores totales',
    'pending_sellers' => 'Pendientes',
    'approved_sellers' => 'Aprobados',
    'rejected_sellers' => 'Rechazados',
    'manage_sellers' => 'Gestionar vendedores',
    'approve' => 'Aprobar',
    'reject' => 'Rechazar',
    'seller_approved' => 'Vendedor aprobado.',
    'seller_rejected' => 'Vendedor rechazado.',
    'no_sellers' => 'No se encontraron vendedores.',
    'filter_all' => 'Todos',
    'filter_pending' => 'Pendientes',
    'filter_approved' => 'Aprobados',
    'filter_rejected' => 'Rechazados',
    'status_pending' => 'Pendiente',
    'status_approved' => 'Aprobado',
    'status_rejected' => 'Rechazado',
];
```

- [ ] **Step 8: Create Spanish `auth.php` and `validation.php` stubs**

Create `lang/es/auth.php`:
```php
<?php

return [
    'failed' => 'Estas credenciales no coinciden con nuestros registros.',
    'password' => 'La contraseña es incorrecta.',
    'throttle' => 'Demasiados intentos de acceso. Intenta de nuevo en :seconds segundos.',
];
```

Create `lang/es/validation.php` (minimal — the keys actually used by the forms):
```php
<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'email' => 'El campo :attribute debe ser un correo válido.',
    'unique' => 'El :attribute ya está en uso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'max' => ['string' => 'El campo :attribute no puede tener más de :max caracteres.'],
    'min' => ['string' => 'El campo :attribute debe tener al menos :min caracteres.'],
    'attributes' => [
        'name' => 'nombre',
        'email' => 'correo',
        'phone' => 'teléfono',
        'password' => 'contraseña',
    ],
];
```

- [ ] **Step 9: Run the locale tests to verify they pass**

Run: `php artisan test --filter=LocaleTest`
Expected: PASS

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat: EN/ES localization with locale switch and message catalogs"
```

---

## Task 9: Brand the landing page (Bold Navy) + public layout

**Files:**
- Create: `resources/views/landing.blade.php`, `resources/views/components/public-layout.blade.php`, `public/images/voicecentra_icon.svg`, `public/images/voicecentra_icon_white.svg`, `public/images/voicecentra_wordmark.svg`
- Modify: `routes/web.php`, `tailwind.config.js`
- Test: `tests/Feature/LandingTest.php`

- [ ] **Step 1: Write a failing test for the landing page**

Create `tests/Feature/LandingTest.php`:
```php
<?php

test('the landing page renders with the hero headline and CTAs', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(__('messages.hero_title'))
        ->assertSee(__('messages.become_seller'))
        ->assertSee('VoiceCentra');
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=LandingTest`
Expected: FAIL — `/` still returns Breeze's default `welcome` view (no `hero_title`).

- [ ] **Step 3: Add brand colors to Tailwind**

In `tailwind.config.js`, extend the theme colors:
```js
theme: {
    extend: {
        colors: {
            brand: {
                blue: '#154CB6',
                navy: '#0A1130',
            },
        },
        fontFamily: {
            sans: ['Figtree', 'ui-sans-serif', 'system-ui', 'sans-serif'],
        },
    },
},
```

- [ ] **Step 4: Copy logo assets into `public/images/`**

Run:
```bash
mkdir -p public/images
cp brand-assets/voicecentra_icon_color.svg public/images/voicecentra_icon.svg
cp brand-assets/voicecentra_wordmark_color.svg public/images/voicecentra_wordmark.svg
```
Then create `public/images/voicecentra_icon_white.svg` (white circle variant for the dark nav):
```svg
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 137.23 137.23"><g transform="translate(-18.98,-11.03)"><path d="M44 36 L88 124 L116 63" fill="none" stroke="#154CB6" stroke-width="28" stroke-linecap="round" stroke-linejoin="round"/><circle cx="131" cy="36" r="14.5" fill="#FFFFFF"/></g></svg>
```

- [ ] **Step 5: Create the public layout**

Create `resources/views/components/public-layout.blade.php` (anonymous component, so `<x-public-layout>` resolves automatically and `{{ $slot }}` is available):
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('messages.become_seller') }} · VoiceCentra</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-brand-navy antialiased">
    <header class="absolute inset-x-0 top-0 z-30">
        <nav class="max-w-7xl mx-auto px-6 py-5 flex items-center justify-between">
            <a href="{{ route('landing') }}" class="flex items-center gap-2">
                <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="VoiceCentra" class="h-7 w-7">
                <span class="text-white font-bold text-lg">VoiceCentra</span>
            </a>
            <div class="flex items-center gap-4 text-sm">
                <div class="flex items-center gap-1 text-blue-100">
                    <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-white font-semibold' : 'hover:text-white' }}">EN</a>
                    <span class="opacity-40">/</span>
                    <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-white font-semibold' : 'hover:text-white' }}">ES</a>
                </div>
                <a href="{{ route('login') }}" class="text-white/90 hover:text-white">{{ __('messages.log_in') }}</a>
                <a href="{{ route('register') }}" class="bg-brand-blue hover:bg-blue-700 text-white px-4 py-2 rounded-lg font-semibold">
                    {{ __('messages.become_seller') }}
                </a>
            </div>
        </nav>
    </header>

    <main>
        {{ $slot }}
    </main>

    <footer class="bg-brand-navy text-blue-100/70 py-10">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="" class="h-6 w-6">
                <span class="text-white font-semibold">VoiceCentra</span>
            </div>
            <div>© {{ date('Y') }} VoiceCentra. {{ __('messages.footer_rights') }}</div>
        </div>
    </footer>
</body>
</html>
```

- [ ] **Step 6: Create the Bold-Navy landing page**

Create `resources/views/landing.blade.php`:
```blade
<x-public-layout>
    {{-- Hero --}}
    <section class="relative bg-brand-navy overflow-hidden">
        <div class="absolute inset-0 bg-gradient-to-br from-brand-navy via-brand-navy to-[#13205c]"></div>
        <div class="relative max-w-7xl mx-auto px-6 pt-36 pb-24">
            <p class="text-sm font-semibold tracking-widest text-blue-300 uppercase">{{ __('messages.hero_eyebrow') }}</p>
            <h1 class="mt-4 text-4xl sm:text-5xl lg:text-6xl font-extrabold text-white leading-tight max-w-3xl">
                {{ __('messages.hero_title') }}
            </h1>
            <p class="mt-6 text-lg text-blue-100/80 max-w-2xl">{{ __('messages.hero_subtitle') }}</p>
            <div class="mt-10 flex flex-wrap gap-4">
                <a href="{{ route('register') }}" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-7 py-3.5 rounded-xl">
                    {{ __('messages.hero_cta_primary') }} &rarr;
                </a>
                <a href="#how" class="border border-white/20 text-white px-7 py-3.5 rounded-xl hover:bg-white/5">
                    {{ __('messages.hero_cta_secondary') }}
                </a>
            </div>

            {{-- Voice waveform motif --}}
            <div class="mt-16 flex items-end gap-1.5 h-16 max-w-md" aria-hidden="true">
                @foreach ([40,75,55,100,65,90,45,80,35,70,50,95,60,85,42] as $h)
                    <div class="flex-1 rounded-sm
                        @class([
                            'bg-brand-blue' => $loop->index % 3 === 0,
                            'bg-blue-500' => $loop->index % 3 === 1,
                            'bg-sky-400' => $loop->index % 3 === 2,
                        ])"
                        style="height: {{ $h }}%"></div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how" class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.how_title') }}</h2>
            <div class="mt-14 grid gap-8 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="text-center">
                        <div class="mx-auto w-12 h-12 rounded-full bg-brand-blue text-white flex items-center justify-center font-bold text-lg">{{ $n }}</div>
                        <h3 class="mt-5 font-semibold text-brand-navy text-lg">{{ __("messages.how_step{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.how_step{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- What you'll sell --}}
    <section class="bg-slate-50 py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.sell_title') }}</h2>
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="bg-white rounded-2xl p-7 shadow-sm">
                        <div class="w-11 h-11 rounded-xl bg-brand-navy/5 flex items-center justify-center text-brand-blue text-xl">●</div>
                        <h3 class="mt-5 font-semibold text-brand-navy text-lg">{{ __("messages.sell_feature{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.sell_feature{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Why sell with us --}}
    <section class="bg-white py-24">
        <div class="max-w-7xl mx-auto px-6">
            <h2 class="text-3xl font-bold text-brand-navy text-center">{{ __('messages.why_title') }}</h2>
            <div class="mt-14 grid gap-6 md:grid-cols-3">
                @foreach (['1','2','3'] as $n)
                    <div class="border border-gray-100 rounded-2xl p-7">
                        <h3 class="font-semibold text-brand-navy text-lg">{{ __("messages.why_feature{$n}_title") }}</h3>
                        <p class="mt-2 text-gray-500">{{ __("messages.why_feature{$n}_body") }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="bg-brand-navy py-20">
        <div class="max-w-4xl mx-auto px-6 text-center">
            <h2 class="text-3xl font-bold text-white">{{ __('messages.final_cta_title') }}</h2>
            <p class="mt-4 text-blue-100/80">{{ __('messages.final_cta_body') }}</p>
            <a href="{{ route('register') }}" class="mt-8 inline-block bg-brand-blue hover:bg-blue-700 text-white font-semibold px-8 py-4 rounded-xl">
                {{ __('messages.hero_cta_primary') }} &rarr;
            </a>
        </div>
    </section>
</x-public-layout>
```

- [ ] **Step 7: Point `/` at the landing view**

In `routes/web.php`, replace the default `Route::get('/', fn () => view('welcome'));` with:
```php
Route::get('/', fn () => view('landing'))->name('landing');
```

- [ ] **Step 8: Build assets and run the landing test**

Run:
```bash
npm run build && php artisan test --filter=LandingTest
```
Expected: PASS

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat: Bold-Navy VoiceCentra landing page and public layout"
```

---

## Task 10: Brand the auth pages and authenticated nav (language switcher + logo)

**Files:**
- Modify: `resources/views/layouts/guest.blade.php`, `resources/views/layouts/navigation.blade.php`

> Presentation task — verified via existing auth tests (pages still return 200) plus the locale test. No new test.

- [ ] **Step 1: Brand the guest (auth) layout**

Replace the body of `resources/views/layouts/guest.blade.php` so the auth card sits on a navy background with the logo:
```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VoiceCentra</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body>
    <div class="min-h-screen flex flex-col justify-center items-center bg-brand-navy py-10">
        <a href="{{ route('landing') }}" class="flex items-center gap-2 mb-6">
            <img src="{{ asset('images/voicecentra_icon_white.svg') }}" alt="VoiceCentra" class="h-9 w-9">
            <span class="text-white font-bold text-2xl">VoiceCentra</span>
        </a>

        <div class="w-full sm:max-w-md px-8 py-7 bg-white shadow-xl rounded-2xl">
            {{ $slot }}
        </div>

        <div class="mt-6 text-sm text-blue-100/70 flex items-center gap-1">
            <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-white font-semibold' : 'hover:text-white' }}">EN</a>
            <span class="opacity-40">/</span>
            <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-white font-semibold' : 'hover:text-white' }}">ES</a>
        </div>
    </div>
</body>
</html>
```

- [ ] **Step 2: Add the language switcher and brand to the authenticated nav**

In `resources/views/layouts/navigation.blade.php`, inside the top-right area (next to the user dropdown), add a language switcher:
```blade
<div class="hidden sm:flex sm:items-center sm:gap-1 sm:ms-6 text-sm text-gray-500">
    <a href="{{ route('locale.switch', 'en') }}" class="{{ app()->getLocale() === 'en' ? 'text-brand-navy font-semibold' : 'hover:text-brand-navy' }}">EN</a>
    <span class="opacity-40">/</span>
    <a href="{{ route('locale.switch', 'es') }}" class="{{ app()->getLocale() === 'es' ? 'text-brand-navy font-semibold' : 'hover:text-brand-navy' }}">ES</a>
</div>
```
Also update the nav's home link target to `route('dashboard')` (Breeze default) and, if desired, swap the Breeze `<x-application-logo>` usage for the VoiceCentra icon: replace its contents with `<img src="{{ asset('images/voicecentra_icon.svg') }}" class="block h-9 w-auto" alt="VoiceCentra">` in `resources/views/components/application-logo.blade.php`.

- [ ] **Step 3: Add phone to the profile edit form**

In `app/Http/Requests/ProfileUpdateRequest.php`, add a phone rule to the `rules()` array:
```php
'phone' => ['nullable', 'string', 'max:30'],
```
In `resources/views/profile/partials/update-profile-information-form.blade.php`, add a phone field after the email block:
```blade
<div>
    <x-input-label for="phone" :value="__('messages.phone')" />
    <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                  :value="old('phone', $user->phone)" autocomplete="tel" />
    <x-input-error class="mt-2" :messages="$errors->get('phone')" />
</div>
```
(`phone` is already in the `User` `$fillable` from Task 2, so `$request->user()->fill($request->validated())` in `ProfileController@update` persists it with no controller change.)

- [ ] **Step 4: Build assets and run the full suite**

Run:
```bash
npm run build && php artisan test
```
Expected: PASS — all feature tests green.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat: brand auth pages, language switcher, and editable phone on profile"
```

---

## Task 11: Seed the admin and demo sellers

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Write the seeder**

Replace `database/seeders/DatabaseSeeder.php`'s `run()` with:
```php
public function run(): void
{
    User::factory()->create([
        'name' => 'VoiceCentra Admin',
        'email' => env('ADMIN_EMAIL', 'admin@voicecentra.com'),
        'password' => \Illuminate\Support\Facades\Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
        'role' => 'admin',
        'status' => 'approved',
        'phone' => null,
    ]);

    User::factory()->pending()->create([
        'name' => 'Demo Pending Seller',
        'email' => 'pending@voicecentra.com',
    ]);

    User::factory()->approvedSeller()->create([
        'name' => 'Demo Approved Seller',
        'email' => 'approved@voicecentra.com',
    ]);
}
```
Ensure `use App\Models\User;` is present at the top of the file.

- [ ] **Step 2: Run the seeder against MySQL**

Run:
```bash
php artisan migrate:fresh --seed --force
```
Expected: tables rebuilt; admin + two demo sellers inserted, no errors.

- [ ] **Step 3: Manually verify the end-to-end flow**

Run `php artisan serve`, then in a browser:
1. Visit `/` — Bold-Navy landing renders; toggle EN/ES.
2. Click "Become a seller", register — you land on the pending page.
3. Log out, log in as the admin (`ADMIN_EMAIL` / `ADMIN_PASSWORD`) — you see the admin dashboard.
4. Open "Manage sellers", Approve the new seller.
5. Log back in as that seller — you reach the seller dashboard.

Expected: every step behaves as described.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: seed admin and demo seller accounts"
```

---

## Final Verification

- [ ] Run the full test suite: `php artisan test` → all green.
- [ ] Run `npm run build` → assets compile with no errors.
- [ ] Confirm the manual flow in Task 11 Step 3 works end to end.
- [ ] Confirm `.env` holds the user's real MySQL credentials and a changed `ADMIN_PASSWORD`.
