# External Sales Ingestion Endpoint Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a JWT-authenticated (HS256, shared secret) JSON endpoint `POST /api/external-sales` that lets an external system record sales for sellers into a new dedicated `external_sales` table.

**Architecture:** A new `external_sales` table + `ExternalSale` model store the incoming records, kept independent of the commission `sales` flow. API routing is added to `bootstrap/app.php` (no Sanctum). A `VerifyExternalJwt` middleware validates the bearer token with `firebase/php-jwt` against a secret in config/env; `ExternalSaleController@store` validates the JSON body, converts the decimal amount to integer cents, and stores the row.

**Tech Stack:** Laravel 11, firebase/php-jwt, Pest on in-memory SQLite, MariaDB live.

**Branch:** create `build/external-sales-endpoint` off `main` before Task 1.

---

## File Structure

**Created:**
- `database/migrations/xxxx_create_external_sales_table.php`
- `app/Models/ExternalSale.php`
- `database/factories/ExternalSaleFactory.php` (test convenience)
- `config/external_sales.php`
- `app/Http/Middleware/VerifyExternalJwt.php`
- `routes/api.php`
- `app/Http/Controllers/Api/ExternalSaleController.php`
- `tests/Feature/ExternalSaleEndpointTest.php`

**Modified:**
- `composer.json` (via `composer require firebase/php-jwt`)
- `bootstrap/app.php` — register `api` routes + the `external.jwt` middleware alias
- `.env`, `.env.example` — `EXTERNAL_SALES_JWT_SECRET`

---

## Task 0: Branch

- [ ] From a clean `main`: `git checkout -b build/external-sales-endpoint`

---

## Task 1: Table, model, factory, config, JWT dependency

**Files:**
- Create: migration, `app/Models/ExternalSale.php`, `database/factories/ExternalSaleFactory.php`, `config/external_sales.php`
- Modify: `.env`, `.env.example`
- Test: `tests/Feature/ExternalSaleEndpointTest.php` (first test)

- [ ] **Step 1: Install firebase/php-jwt**

Run:
```bash
composer require firebase/php-jwt --no-interaction
```
Expected: package added to `composer.json` require section, no errors.

- [ ] **Step 2: Write the failing test**

Create `tests/Feature/ExternalSaleEndpointTest.php`:
```php
<?php

use App\Models\ExternalSale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('an external sale persists with cents and boolean casts', function () {
    $seller = User::factory()->approvedSeller()->create();

    $sale = ExternalSale::create([
        'seller_id' => $seller->id,
        'sale_date' => '2026-06-20',
        'paid_at' => '2026-06-21 14:30:00',
        'amount_cents' => 4999,
        'paid' => true,
        'free_trial' => false,
    ]);

    $fresh = $sale->fresh();
    expect($fresh->amount_cents)->toBe(4999);
    expect($fresh->paid)->toBeTrue();
    expect($fresh->free_trial)->toBeFalse();
    expect($fresh->sale_date->format('Y-m-d'))->toBe('2026-06-20');
    expect($fresh->seller->id)->toBe($seller->id);
});
```

- [ ] **Step 3: Run to verify failure**

Run: `php artisan test --filter=ExternalSaleEndpointTest`
Expected: FAIL — `ExternalSale` model / table missing.

- [ ] **Step 4: Create the migration**

Run `php artisan make:migration create_external_sales_table`, then set contents to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('external_sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->date('sale_date');
            $table->dateTime('paid_at')->nullable();
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('paid')->default(false);
            $table->boolean('free_trial')->default(false);
            $table->timestamps();
            $table->index(['seller_id', 'sale_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_sales');
    }
};
```

- [ ] **Step 5: Create the model**

Create `app/Models/ExternalSale.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExternalSale extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id', 'sale_date', 'paid_at', 'amount_cents', 'paid', 'free_trial',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'paid_at' => 'datetime',
            'amount_cents' => 'integer',
            'paid' => 'boolean',
            'free_trial' => 'boolean',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }
}
```

- [ ] **Step 6: Create the factory**

Create `database/factories/ExternalSaleFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExternalSaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->approvedSeller(),
            'sale_date' => now()->subDays(fake()->numberBetween(0, 30))->toDateString(),
            'paid_at' => now(),
            'amount_cents' => fake()->numberBetween(1000, 500000),
            'paid' => true,
            'free_trial' => false,
        ];
    }
}
```

- [ ] **Step 7: Create the config file**

Create `config/external_sales.php`:
```php
<?php

return [
    'jwt_secret' => env('EXTERNAL_SALES_JWT_SECRET'),
];
```

- [ ] **Step 8: Add the env var**

Append to `.env` and `.env.example`:
```dotenv
EXTERNAL_SALES_JWT_SECRET=
```
In `.env` (local) set a real value, e.g. `EXTERNAL_SALES_JWT_SECRET=local-dev-shared-secret-change-me`; leave the `.env.example` value empty.

- [ ] **Step 9: Run tests**

Run: `php artisan test --filter=ExternalSaleEndpointTest` then full suite `php artisan test`.
Expected: the model test PASSES; full suite stays green.

- [ ] **Step 10: Commit**

```bash
git add -A
git commit -m "feat(api): external_sales table, model, factory, config and php-jwt dep"
```

---

## Task 2: API routing + JWT middleware

**Files:**
- Create: `routes/api.php`, `app/Http/Middleware/VerifyExternalJwt.php`
- Modify: `bootstrap/app.php`
- Test: `tests/Feature/ExternalSaleEndpointTest.php` (append auth tests)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ExternalSaleEndpointTest.php`:
```php
use Firebase\JWT\JWT;

function validToken(array $claims = []): string
{
    config(['external_sales.jwt_secret' => 'test-secret']);

    return JWT::encode(
        array_merge(['iss' => 'external', 'iat' => time(), 'exp' => time() + 3600], $claims),
        'test-secret',
        'HS256'
    );
}

function validPayload(int $sellerId): array
{
    return [
        'seller_id' => $sellerId,
        'sale_date' => '2026-06-20',
        'paid_at' => '2026-06-21T14:30:00Z',
        'amount' => 49.99,
        'paid' => true,
        'free_trial' => false,
    ];
}

test('a request without a token is rejected with 401', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('a request with a malformed token is rejected with 401', function () {
    config(['external_sales.jwt_secret' => 'test-secret']);
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken('not-a-jwt')
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('a token signed with the wrong secret is rejected with 401', function () {
    config(['external_sales.jwt_secret' => 'test-secret']);
    $seller = User::factory()->approvedSeller()->create();
    $bad = JWT::encode(['exp' => time() + 3600], 'WRONG-secret', 'HS256');

    $this->withToken($bad)
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});

test('an expired token is rejected with 401', function () {
    $seller = User::factory()->approvedSeller()->create();
    $expired = validToken(['exp' => time() - 10]);

    $this->withToken($expired)
        ->postJson('/api/external-sales', validPayload($seller->id))
        ->assertStatus(401);
});
```
(These will fully pass once Task 3 adds the controller, but the 401 behavior is the middleware's responsibility and is provable now: with no controller/route the path 404s, so this step's RED is "404/500, not 401". After Task 2 wires the route to a temporary closure returning 200, the 401 tests pass and the others get 200.)

To make Task 2 independently verifiable, the route in Step 4 below points at a temporary closure returning `response()->json(['ok' => true], 200)`; Task 3 replaces it with the controller.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=ExternalSaleEndpointTest`
Expected: the four auth tests FAIL (route not defined → 404, not 401).

- [ ] **Step 3: Create the middleware**

Create `app/Http/Middleware/VerifyExternalJwt.php`:
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyExternalJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $secret = config('external_sales.jwt_secret');

        if (! $token || ! $secret) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            // bad signature, expired (exp), not-yet-valid (nbf), malformed, etc.
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return $next($request);
    }
}
```

- [ ] **Step 4: Create `routes/api.php` (temporary closure for now)**

Create `routes/api.php`:
```php
<?php

use Illuminate\Support\Facades\Route;

Route::middleware('external.jwt')->group(function () {
    // Replaced by ExternalSaleController@store in Task 3.
    Route::post('/external-sales', fn () => response()->json(['ok' => true], 200));
});
```

- [ ] **Step 5: Register API routing + middleware alias in `bootstrap/app.php`**

In `bootstrap/app.php`, update `->withRouting(...)` to add the `api` entry:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```
And in the `->withMiddleware(function (Middleware $middleware) { ... })` closure, add to the existing `$middleware->alias([...])` array:
```php
'external.jwt' => \App\Http\Middleware\VerifyExternalJwt::class,
```
(Keep the existing `admin` and `seller.approved` aliases and the `SetLocale` web append.)

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=ExternalSaleEndpointTest`
Expected: the four 401 auth tests PASS (no-token, malformed, wrong-secret, expired all 401). The Task 1 model test still passes. Full suite green.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(api): JWT-verifying api route group (external.jwt middleware)"
```

---

## Task 3: Controller — validate, convert, store

**Files:**
- Create: `app/Http/Controllers/Api/ExternalSaleController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/ExternalSaleEndpointTest.php` (append success/validation tests)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/ExternalSaleEndpointTest.php`:
```php
test('a valid request records an external sale and returns 201', function () {
    $seller = User::factory()->approvedSeller()->create();

    $response = $this->withToken(validToken())
        ->postJson('/api/external-sales', validPayload($seller->id));

    $response->assertStatus(201)->assertJson(['status' => 'recorded']);

    $sale = ExternalSale::first();
    expect($sale->seller_id)->toBe($seller->id);
    expect($sale->amount_cents)->toBe(4999);     // 49.99 -> cents
    expect($sale->paid)->toBeTrue();
    expect($sale->free_trial)->toBeFalse();
    expect($sale->sale_date->format('Y-m-d'))->toBe('2026-06-20');
    expect($response->json('id'))->toBe($sale->id);
});

test('a whole-number amount converts to cents correctly', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [...validPayload($seller->id), 'amount' => 100])
        ->assertStatus(201);

    expect(ExternalSale::first()->amount_cents)->toBe(10000);
});

test('an unpaid sale with null paid_at is accepted', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [
            ...validPayload($seller->id), 'paid' => false, 'paid_at' => null,
        ])
        ->assertStatus(201);

    $sale = ExternalSale::first();
    expect($sale->paid)->toBeFalse();
    expect($sale->paid_at)->toBeNull();
});

test('a missing required field returns 422', function () {
    $seller = User::factory()->approvedSeller()->create();
    $payload = validPayload($seller->id);
    unset($payload['amount']);

    $this->withToken(validToken())
        ->postJson('/api/external-sales', $payload)
        ->assertStatus(422)->assertJsonValidationErrors('amount');
});

test('an unknown seller_id returns 422', function () {
    $this->withToken(validToken())
        ->postJson('/api/external-sales', validPayload(999999))
        ->assertStatus(422)->assertJsonValidationErrors('seller_id');
});

test('a negative amount returns 422', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->withToken(validToken())
        ->postJson('/api/external-sales', [...validPayload($seller->id), 'amount' => -5])
        ->assertStatus(422)->assertJsonValidationErrors('amount');
});
```
NOTE on the PHP spread (`[...validPayload($id), 'amount' => 100]`): array spread with string keys requires PHP 8.1+; this project is PHP 8.2+, so it is fine. The later key (`'amount'`) overrides the spread value.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=ExternalSaleEndpointTest`
Expected: the new success/validation tests FAIL (temporary closure returns 200 `{ok:true}`, not 201/422; nothing is stored).

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Api/ExternalSaleController.php`:
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExternalSale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalSaleController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'seller_id' => ['required', 'integer', 'exists:users,id'],
            'sale_date' => ['required', 'date'],
            'paid_at' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0'],
            'paid' => ['required', 'boolean'],
            'free_trial' => ['required', 'boolean'],
        ]);

        $sale = ExternalSale::create([
            'seller_id' => $data['seller_id'],
            'sale_date' => $data['sale_date'],
            'paid_at' => $data['paid_at'] ?? null,
            'amount_cents' => (int) round($data['amount'] * 100),
            'paid' => $data['paid'],
            'free_trial' => $data['free_trial'],
        ]);

        return response()->json(['id' => $sale->id, 'status' => 'recorded'], 201);
    }
}
```

- [ ] **Step 4: Point the route at the controller**

Replace the temporary closure in `routes/api.php` so the file reads:
```php
<?php

use App\Http\Controllers\Api\ExternalSaleController;
use Illuminate\Support\Facades\Route;

Route::middleware('external.jwt')->group(function () {
    Route::post('/external-sales', [ExternalSaleController::class, 'store']);
});
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ExternalSaleEndpointTest` then full suite `php artisan test`.
Expected: ALL external-sale tests PASS (auth 401s, success 201 + stored cents, validation 422s); full suite green.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(api): ExternalSaleController stores incoming sales (decimal->cents, 201/422)"
```

---

## Task 4: Build + final verification

- [ ] **Step 1: Run the full suite**

Run: `php artisan test`
Expected: all green.

- [ ] **Step 2: Confirm the route is registered**

Run: `php artisan route:list --path=api/external-sales`
Expected: `POST api/external-sales … external.jwt` listed.

---

## Final Verification

- [ ] `php artisan test` → all green.
- [ ] **Live DB (MariaDB):** `php artisan migrate --force` (NEVER `migrate:fresh` — the live DB has real accounts).
- [ ] Set a real `EXTERNAL_SALES_JWT_SECRET` in the live `.env`.
- [ ] Manual smoke test with `php artisan serve` (mint a token with the same secret):
  ```bash
  TOKEN=$(php -r 'require "vendor/autoload.php"; use Firebase\JWT\JWT; echo JWT::encode(["iss"=>"ext","iat"=>time(),"exp"=>time()+3600], getenv("SECRET"), "HS256");' SECRET="<your .env secret>")
  curl -s -X POST http://127.0.0.1:8000/api/external-sales \
    -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
    -d '{"seller_id":2,"sale_date":"2026-06-20","paid_at":"2026-06-21T14:30:00Z","amount":49.99,"paid":true,"free_trial":false}'
  # expect: {"id":1,"status":"recorded"}
  curl -s -X POST http://127.0.0.1:8000/api/external-sales -d '{}'   # no token -> 401
  ```
- [ ] Confirm the row landed: `SELECT seller_id, amount_cents, paid, free_trial FROM external_sales;` shows `amount_cents = 4999`.
