# Seller Commission System Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a multi-level commission system to the VoiceCentra Sellers Portal: seller sponsorship tree (referral links, max depth 10), seller-submitted sales with admin approval, exact integer-cents commission distribution with an activity gate and bonus pool, network tree visualization on both dashboards, and a commission calculator page.

**Architecture:** Adjacency-list tree (`parent_id` + cached `depth` on `users`) read via recursive CTEs. A pure `CommissionCalculator` service does all the math (every rate is n/5120 of the sale; integer cents only); a `CommissionDistributor` service loads the chain + activity data and persists immutable payout/pool ledgers in a transaction when an admin approves a sale. The calculator page calls the same `CommissionCalculator`.

**Tech Stack:** Laravel 11, Breeze (Blade + Tailwind + Alpine), Pest on in-memory SQLite (SQLite and MariaDB 10.4 both support recursive CTEs), MariaDB live DB.

**Branch:** create `build/commission-system` off `main` before Task 1.

**Money convention:** all stored amounts are integer cents. Display via `Illuminate\Support\Number::currency($cents / 100)`. Rates are exact binary fractions over denominator 5120: seller 512, L1 256, L2 128, L3 64, L4 32, L5 16, L6 8, L7 4, L8 2, L9 1; total 1023. Every payout is `intdiv($cents * $numerator, 5120)`; the pool absorbs the remainder.

---

## File Structure

**Created:**
- `config/commissions.php` — all constants
- `database/migrations/xxxx_add_tree_to_users_table.php` — parent_id, depth, referral_code (+ backfill)
- `database/migrations/xxxx_create_sales_table.php`
- `database/migrations/xxxx_create_commission_payouts_table.php`
- `database/migrations/xxxx_create_bonus_pool_entries_table.php`
- `app/Models/Sale.php`, `app/Models/CommissionPayout.php`, `app/Models/BonusPoolEntry.php`
- `database/factories/SaleFactory.php`
- `app/Services/CommissionCalculator.php` — pure math, no DB
- `app/Services/CommissionDistributor.php` — distribute/refund/isActive
- `app/Services/SellerTree.php` — subtree/forest/guards/recache
- `app/Http/Controllers/SellerNetworkController.php`
- `app/Http/Controllers/SellerSaleController.php`
- `app/Http/Controllers/SellerCommissionController.php`
- `app/Http/Controllers/CalculatorController.php`
- `app/Http/Controllers/Admin/AdminNetworkController.php`
- `app/Http/Controllers/Admin/AdminSaleController.php`
- `app/Http/Controllers/Admin/AdminBonusPoolController.php`
- `resources/views/partials/seller-tree-node.blade.php` — recursive tree node
- `resources/views/seller/network.blade.php`, `resources/views/seller/sales/index.blade.php`, `resources/views/seller/commissions.blade.php`
- `resources/views/admin/network.blade.php`, `resources/views/admin/sales/index.blade.php`, `resources/views/admin/bonus-pool.blade.php`, `resources/views/admin/sellers/sponsor.blade.php`
- `resources/views/calculator.blade.php`
- `database/seeders/CommissionDemoSeeder.php`
- Tests: `tests/Unit/CommissionCalculatorTest.php`, `tests/Feature/SellerTreeTest.php`, `tests/Feature/ReferralRegistrationTest.php`, `tests/Feature/CommissionDistributionTest.php`, `tests/Feature/SaleSubmissionTest.php`, `tests/Feature/AdminSalesTest.php`, `tests/Feature/NetworkPagesTest.php`, `tests/Feature/CommissionPagesTest.php`, `tests/Feature/SponsorChangeTest.php`, `tests/Feature/CalculatorTest.php`, `tests/Feature/LocaleParityTest.php`

**Modified:**
- `app/Models/User.php` — tree relations, referral code, upline chain
- `app/Http/Controllers/Auth/RegisteredUserController.php` — referral handling
- `resources/views/auth/register.blade.php` — sponsor banner + hidden ref
- `app/Http/Controllers/SellerDashboardController.php` + `resources/views/seller/dashboard.blade.php` — earnings cards, referral link
- `app/Http/Controllers/Admin/AdminDashboardController.php` + `resources/views/admin/dashboard.blade.php` — sales/pool cards
- `app/Http/Controllers/Admin/AdminSellerController.php` + `resources/views/admin/sellers/index.blade.php` — sponsor column + change-sponsor
- `routes/web.php` — new routes
- `resources/views/layouts/navigation.blade.php` — role-aware links
- `lang/en/messages.php`, `lang/es/messages.php` — new keys
- `database/seeders/DatabaseSeeder.php` — call demo seeder

---

## Task 0: Branch

- [ ] **Step 1:** From a clean `main`: `git checkout -b build/commission-system`

---

## Task 1: Config, migrations, models, factories

**Files:**
- Create: `config/commissions.php`, 4 migrations, `app/Models/Sale.php`, `app/Models/CommissionPayout.php`, `app/Models/BonusPoolEntry.php`, `database/factories/SaleFactory.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/SellerTreeTest.php` (first tests)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/SellerTreeTest.php`:
```php
<?php

use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('users get a unique referral code on creation', function () {
    $a = User::factory()->approvedSeller()->create();
    $b = User::factory()->approvedSeller()->create();

    expect($a->referral_code)->toHaveLength(8);
    expect($b->referral_code)->not->toBe($a->referral_code);
});

test('parent and children relations work with cached depth', function () {
    $root = User::factory()->approvedSeller()->create();
    $child = User::factory()->approvedSeller()->withSponsor($root)->create();

    expect($child->parent->id)->toBe($root->id);
    expect($root->children->pluck('id')->all())->toBe([$child->id]);
    expect($root->depth)->toBe(1);
    expect($child->depth)->toBe(2);
});

test('upline chain walks at most nine levels', function () {
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i <= 10; $i++) {
        $users[$i] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    // users[10] has 10 ancestors but the chain caps at 9
    $chain = $users[10]->uplineChain();

    expect($chain)->toHaveCount(9);
    expect($chain[1]->id)->toBe($users[9]->id);
    expect($chain[9]->id)->toBe($users[1]->id);
});

test('a sale can be created with cents and casts sold_at', function () {
    $sale = Sale::factory()->create(['amount_cents' => 123456]);

    expect($sale->amount_cents)->toBe(123456);
    expect($sale->sold_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
    expect($sale->status)->toBe('pending');
});
```

Note: `withSponsor()` exceeds depth 10 in the third test on purpose — the factory state sets parent/depth mechanically; the depth-10 *enforcement* lives in registration and sponsor-change (Tasks 5, 12), not in the factory.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SellerTreeTest`
Expected: FAIL — `referral_code` column missing / `withSponsor` undefined.

- [ ] **Step 3: Create `config/commissions.php`**

```php
<?php

return [
    // All rates are exact fractions over this denominator.
    'denominator' => 5120,
    'seller_numerator' => 512,           // 10%
    'level_numerators' => [
        1 => 256,  // 5%
        2 => 128,  // 2.5%
        3 => 64,   // 1.25%
        4 => 32,   // 0.625%
        5 => 16,   // 0.3125%
        6 => 8,    // 0.15625%
        7 => 4,    // 0.078125%
        8 => 2,    // 0.0390625%
        9 => 1,    // 0.01953125%
    ],
    'total_numerator' => 1023,           // 19.98046875% total charge per sale

    'max_depth' => 10,                   // max people in a chain (seller + 9 uplines)
    'auto_levels' => 3,                  // upline levels 1..3 always pay
    'min_sales_quarter' => (int) env('COMMISSION_MIN_SALES_QUARTER', 2),
    'activity_window_days' => 90,
];
```

- [ ] **Step 4: Create the four migrations**

Run:
```bash
php artisan make:migration add_tree_to_users_table
php artisan make:migration create_sales_table
php artisan make:migration create_commission_payouts_table
php artisan make:migration create_bonus_pool_entries_table
```

`add_tree_to_users_table`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('approved_by')
                ->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('depth')->default(1)->after('parent_id');
            $table->string('referral_code', 8)->nullable()->unique()->after('depth');
        });

        // Backfill referral codes for existing users.
        foreach (DB::table('users')->whereNull('referral_code')->pluck('id') as $id) {
            do {
                $code = strtoupper(Str::random(8));
            } while (DB::table('users')->where('referral_code', $code)->exists());
            DB::table('users')->where('id', $id)->update(['referral_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['depth', 'referral_code']);
        });
    }
};
```

`create_sales_table`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_cents');
            $table->dateTime('sold_at');
            $table->enum('status', ['pending', 'approved', 'rejected', 'refunded'])->default('pending');
            $table->string('notes')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->index(['seller_id', 'status', 'sold_at']); // activity-gate query
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
```

`create_commission_payouts_table`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('commission_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('level'); // 0 = seller, 1..9 = upline
            $table->unsignedSmallInteger('rate_numerator'); // n of n/5120
            $table->unsignedBigInteger('amount_cents');
            $table->boolean('recipient_was_active')->default(true);
            $table->enum('status', ['paid', 'reversed'])->default('paid');
            $table->timestamps();
            $table->index(['recipient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_payouts');
    }
};
```

`create_bonus_pool_entries_table`:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bonus_pool_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->unsignedTinyInteger('level')->nullable(); // null = rounding/reversal row
            $table->bigInteger('amount_cents'); // signed: negative on refund reversal
            $table->enum('reason', ['no_upline', 'inactive_upline', 'rounding', 'refund_reversal']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_pool_entries');
    }
};
```

- [ ] **Step 5: Create the three models**

`app/Models/Sale.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use HasFactory;

    // seller_id / status / approved_* are set explicitly, never mass-assigned.
    protected $fillable = ['amount_cents', 'sold_at', 'notes'];

    protected function casts(): array
    {
        return [
            'sold_at' => 'datetime',
            'approved_at' => 'datetime',
            'amount_cents' => 'integer',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(CommissionPayout::class);
    }

    public function poolEntries(): HasMany
    {
        return $this->hasMany(BonusPoolEntry::class);
    }
}
```

`app/Models/CommissionPayout.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionPayout extends Model
{
    // Only ever created by CommissionDistributor (server-side), never from request input.
    protected $fillable = [
        'sale_id', 'recipient_id', 'level', 'rate_numerator',
        'amount_cents', 'recipient_was_active', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'recipient_was_active' => 'boolean',
        ];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
```

`app/Models/BonusPoolEntry.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BonusPoolEntry extends Model
{
    protected $fillable = ['sale_id', 'level', 'amount_cents', 'reason'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
```

- [ ] **Step 6: Extend the User model**

In `app/Models/User.php` add the imports `use Illuminate\Database\Eloquent\Relations\BelongsTo;`, `use Illuminate\Database\Eloquent\Relations\HasMany;`, `use Illuminate\Support\Str;`, then add after `isRejected()`:

```php
protected static function booted(): void
{
    static::creating(function (User $user) {
        if (empty($user->referral_code)) {
            $user->referral_code = static::generateReferralCode();
        }
    });
}

public static function generateReferralCode(): string
{
    do {
        $code = strtoupper(Str::random(8));
    } while (static::where('referral_code', $code)->exists());

    return $code;
}

public function parent(): BelongsTo
{
    return $this->belongsTo(User::class, 'parent_id');
}

public function children(): HasMany
{
    return $this->hasMany(User::class, 'parent_id');
}

public function sales(): HasMany
{
    return $this->hasMany(Sale::class, 'seller_id');
}

public function commissionPayouts(): HasMany
{
    return $this->hasMany(CommissionPayout::class, 'recipient_id');
}

public function referralLink(): string
{
    return route('register', ['ref' => $this->referral_code]);
}

/**
 * The seller's uplines, level (1..9) => User. Level 1 is the direct sponsor.
 *
 * @return array<int, User>
 */
public function uplineChain(): array
{
    $chain = [];
    $current = $this->parent;
    $level = 1;

    while ($current !== null && $level <= count(config('commissions.level_numerators'))) {
        $chain[$level] = $current;
        $current = $current->parent;
        $level++;
    }

    return $chain;
}
```

(`parent_id`, `depth`, `referral_code` stay OUT of `$fillable` — same least-privilege pattern as `role`/`status`.)

- [ ] **Step 7: Factories**

Add a `withSponsor` state to `database/factories/UserFactory.php`:
```php
public function withSponsor(\App\Models\User $sponsor): static
{
    return $this->state(fn () => [
        'parent_id' => $sponsor->id,
        'depth' => $sponsor->depth + 1,
    ]);
}
```

Create `database/factories/SaleFactory.php`:
```php
<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SaleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'seller_id' => User::factory()->approvedSeller(),
            'amount_cents' => fake()->numberBetween(50_00, 5_000_00),
            'sold_at' => now()->subDays(fake()->numberBetween(0, 30)),
            'status' => 'pending',
            'notes' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => ['status' => 'approved', 'approved_at' => now()]);
    }
}
```
(Factories run unguarded, so setting non-fillable columns here is fine — same as the existing UserFactory.)

- [ ] **Step 8: Run tests**

Run: `php artisan test --filter=SellerTreeTest` then `php artisan test`
Expected: new tests PASS; full suite stays green (49 + 4).

- [ ] **Step 9: Commit**

```bash
git add -A
git commit -m "feat(commissions): tree columns, sales/payout/pool schema, models and factories"
```

---

## Task 2: CommissionCalculator (pure math)

**Files:**
- Create: `app/Services/CommissionCalculator.php`
- Test: `tests/Unit/CommissionCalculatorTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/CommissionCalculatorTest.php`:
```php
<?php

use App\Services\CommissionCalculator;

function calc(): CommissionCalculator
{
    return new CommissionCalculator();
}

test('full active chain on a $1000 sale splits exactly', function () {
    $r = calc()->calculate(100_000, [
        1 => true, 2 => true, 3 => true, 4 => true, 5 => true,
        6 => true, 7 => true, 8 => true, 9 => true,
    ]);

    expect($r['seller_cents'])->toBe(10_000);                 // 10%
    expect($r['levels'][1]['amount_cents'])->toBe(5_000);     // 5%
    expect($r['levels'][2]['amount_cents'])->toBe(2_500);
    expect($r['levels'][3]['amount_cents'])->toBe(1_250);
    expect($r['levels'][4]['amount_cents'])->toBe(625);
    expect($r['levels'][5]['amount_cents'])->toBe(312);       // 312.5 floored
    expect($r['levels'][6]['amount_cents'])->toBe(156);
    expect($r['levels'][7]['amount_cents'])->toBe(78);
    expect($r['levels'][8]['amount_cents'])->toBe(39);
    expect($r['levels'][9]['amount_cents'])->toBe(19);
    expect($r['total_charge_cents'])->toBe(19_980);           // floor(100000*1023/5120)
    expect($r['pool_rounding_cents'])->toBe(1);
    expect($r['pool_total_cents'])->toBe(1);
    expect(collect($r['levels'])->every(fn ($l) => $l['paid']))->toBeTrue();
});

test('a short chain sends missing levels to the pool', function () {
    $r = calc()->calculate(50_000, [1 => true, 2 => true, 3 => true]); // only 3 uplines

    expect($r['levels'][3]['paid'])->toBeTrue();
    expect($r['levels'][4]['paid'])->toBeFalse();
    expect($r['levels'][4]['pool_reason'])->toBe('no_upline');
    // missing L4..L9: 312+156+78+39+19+9 = 613; total 9990; paid 9375; rounding 2
    expect($r['total_charge_cents'])->toBe(9_990);
    expect($r['pool_total_cents'])->toBe(615);
});

test('inactive uplines at level 4+ are skipped; levels 1-3 pay even if inactive', function () {
    $r = calc()->calculate(100_000, [
        1 => false, 2 => false, 3 => false, 4 => false, 5 => true,
    ]);

    expect($r['levels'][1]['paid'])->toBeTrue();   // auto level
    expect($r['levels'][3]['paid'])->toBeTrue();   // auto level
    expect($r['levels'][4]['paid'])->toBeFalse();
    expect($r['levels'][4]['pool_reason'])->toBe('inactive_upline');
    expect($r['levels'][5]['paid'])->toBeTrue();
    expect($r['levels'][6]['pool_reason'])->toBe('no_upline');
});

test('the invariant reconciles for awkward amounts', function (int $cents) {
    foreach ([[], [1 => true], [1 => true, 2 => false, 3 => true, 4 => false, 5 => true, 6 => false, 7 => true, 8 => false, 9 => true]] as $slots) {
        $r = calc()->calculate($cents, $slots);
        $paidLevels = collect($r['levels'])->where('paid', true)->sum('amount_cents');

        expect($r['seller_cents'] + $paidLevels + $r['pool_total_cents'])
            ->toBe($r['total_charge_cents']);
        expect($r['pool_rounding_cents'])->toBeGreaterThanOrEqual(0);
    }
})->with([1, 99, 9_999, 100_000, 123_457, 999_999_99]);

test('a one-cent sale produces all zeros and still reconciles', function () {
    $r = calc()->calculate(1, [1 => true]);

    expect($r['seller_cents'])->toBe(0);
    expect($r['total_charge_cents'])->toBe(0);
    expect($r['pool_total_cents'])->toBe(0);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=CommissionCalculatorTest`
Expected: FAIL — class not found.

- [ ] **Step 3: Implement `app/Services/CommissionCalculator.php`**

```php
<?php

namespace App\Services;

/**
 * Pure commission math. No database access.
 *
 * All rates are exact fractions over config('commissions.denominator') (5120).
 * Amounts are integer cents; each payout is floored and the bonus pool absorbs
 * skipped levels, missing levels, and the rounding remainder, so:
 *   seller + paid uplines + pool == total_charge, exactly, for every input.
 */
class CommissionCalculator
{
    /**
     * @param int $amountCents sale amount in cents
     * @param array<int, bool> $uplineSlots level (1..9) => whether that upline is
     *        active. A missing level means the chain has no upline there.
     * @return array{
     *   seller_cents: int,
     *   levels: array<int, array{exists: bool, paid: bool, amount_cents: int, pool_reason: ?string}>,
     *   pool_rounding_cents: int,
     *   pool_total_cents: int,
     *   total_charge_cents: int
     * }
     */
    public function calculate(int $amountCents, array $uplineSlots): array
    {
        $den = (int) config('commissions.denominator');
        $autoLevels = (int) config('commissions.auto_levels');

        $sellerCents = intdiv($amountCents * (int) config('commissions.seller_numerator'), $den);
        $totalCharge = intdiv($amountCents * (int) config('commissions.total_numerator'), $den);

        $levels = [];
        $paidSum = $sellerCents;
        $poolFromLevels = 0;

        foreach (config('commissions.level_numerators') as $level => $numerator) {
            $amount = intdiv($amountCents * $numerator, $den);
            $exists = array_key_exists($level, $uplineSlots);
            $paid = $exists && ($level <= $autoLevels || $uplineSlots[$level]);

            $levels[$level] = [
                'exists' => $exists,
                'paid' => $paid,
                'amount_cents' => $amount,
                'pool_reason' => $paid ? null : ($exists ? 'inactive_upline' : 'no_upline'),
            ];

            if ($paid) {
                $paidSum += $amount;
            } else {
                $poolFromLevels += $amount;
            }
        }

        $rounding = $totalCharge - $paidSum - $poolFromLevels;

        if ($rounding < 0) {
            throw new \LogicException('Commission invariant violated: negative rounding remainder.');
        }

        return [
            'seller_cents' => $sellerCents,
            'levels' => $levels,
            'pool_rounding_cents' => $rounding,
            'pool_total_cents' => $poolFromLevels + $rounding,
            'total_charge_cents' => $totalCharge,
        ];
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CommissionCalculatorTest` then full suite.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(commissions): exact integer CommissionCalculator with invariant"
```

---

## Task 3: SellerTree service

**Files:**
- Create: `app/Services/SellerTree.php`
- Test: `tests/Feature/SellerTreeTest.php` (append)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/SellerTreeTest.php`:
```php
test('subtree returns a nested node with descendant counts', function () {
    $tree = app(\App\Services\SellerTree::class);

    $root = User::factory()->approvedSeller()->create();
    $a = User::factory()->approvedSeller()->withSponsor($root)->create();
    $b = User::factory()->approvedSeller()->withSponsor($root)->create();
    $c = User::factory()->approvedSeller()->withSponsor($a)->create();
    User::factory()->approvedSeller()->create(); // unrelated seller

    $node = $tree->subtree($root);

    expect($node['user']->id)->toBe($root->id);
    expect($node['descendants_count'])->toBe(3);
    expect(collect($node['children'])->pluck('user.id')->sort()->values()->all())
        ->toBe(collect([$a->id, $b->id])->sort()->values()->all());
});

test('forest returns all top-level sellers with their subtrees', function () {
    $tree = app(\App\Services\SellerTree::class);

    $r1 = User::factory()->approvedSeller()->create();
    $r2 = User::factory()->pending()->create();
    User::factory()->approvedSeller()->withSponsor($r1)->create();
    User::factory()->admin()->create(); // admins are not part of the network

    $forest = $tree->forest();

    expect(collect($forest)->pluck('user.id')->sort()->values()->all())
        ->toBe(collect([$r1->id, $r2->id])->sort()->values()->all());
});

test('isInSubtree and subtreeHeight report correctly', function () {
    $tree = app(\App\Services\SellerTree::class);

    $root = User::factory()->approvedSeller()->create();
    $mid = User::factory()->approvedSeller()->withSponsor($root)->create();
    $leaf = User::factory()->approvedSeller()->withSponsor($mid)->create();
    $other = User::factory()->approvedSeller()->create();

    expect($tree->isInSubtree($leaf, $root))->toBeTrue();
    expect($tree->isInSubtree($root, $leaf))->toBeFalse();
    expect($tree->isInSubtree($other, $root))->toBeFalse();
    expect($tree->subtreeHeight($root))->toBe(3);
    expect($tree->subtreeHeight($leaf))->toBe(1);
});

test('changeSponsor moves a subtree and recaches depths', function () {
    $tree = app(\App\Services\SellerTree::class);

    $oldRoot = User::factory()->approvedSeller()->create();
    $mover = User::factory()->approvedSeller()->withSponsor($oldRoot)->create();
    $grandchild = User::factory()->approvedSeller()->withSponsor($mover)->create();
    $newRoot = User::factory()->approvedSeller()->create();
    $newParent = User::factory()->approvedSeller()->withSponsor($newRoot)->create(); // depth 2

    $tree->changeSponsor($mover, $newParent);

    $mover->refresh();
    $grandchild->refresh();
    expect($mover->parent_id)->toBe($newParent->id);
    expect($mover->depth)->toBe(3);
    expect($grandchild->depth)->toBe(4);

    $tree->changeSponsor($mover, null);
    expect($mover->fresh()->depth)->toBe(1);
    expect($grandchild->fresh()->depth)->toBe(2);
});

test('recentSalesCounts groups approved sales inside the window', function () {
    $tree = app(\App\Services\SellerTree::class);

    $seller = User::factory()->approvedSeller()->create();
    Sale::factory()->approved()->count(2)->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(5)]);
    Sale::factory()->approved()->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(120)]); // outside
    Sale::factory()->create(['seller_id' => $seller->id, 'sold_at' => now()->subDays(2)]); // pending

    $counts = $tree->recentSalesCounts(collect([$seller]));

    expect($counts[$seller->id])->toBe(2);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SellerTreeTest`
Expected: FAIL — `SellerTree` not found.

- [ ] **Step 3: Implement `app/Services/SellerTree.php`**

```php
<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SellerTree
{
    /**
     * Every user in the subtree rooted at $rootId, including the root.
     * One recursive CTE for ids (works on MariaDB 10.2+ and SQLite), then hydrate.
     */
    public function subtreeUsers(int $rootId): Collection
    {
        $rows = DB::select(<<<'SQL'
            WITH RECURSIVE subtree AS (
                SELECT id FROM users WHERE id = ?
                UNION ALL
                SELECT u.id FROM users u INNER JOIN subtree s ON u.parent_id = s.id
            )
            SELECT id FROM subtree
        SQL, [$rootId]);

        return User::whereIn('id', array_column($rows, 'id'))->get();
    }

    /**
     * Nested node for one root: ['user' => User, 'children' => [...], 'descendants_count' => int].
     */
    public function subtree(User $root): array
    {
        $users = $this->subtreeUsers($root->id);
        $children = $this->buildNodes($users, $root->id);

        return [
            'user' => $root,
            'children' => $children,
            'descendants_count' => collect($children)->sum(fn ($c) => 1 + $c['descendants_count']),
        ];
    }

    /**
     * All top-level sellers, each as a nested node (admins are not in the network).
     *
     * @return array<int, array>
     */
    public function forest(): array
    {
        return $this->buildNodes(User::where('role', 'seller')->get(), null);
    }

    /**
     * @return array<int, array{user: User, children: array, descendants_count: int}>
     */
    public function buildNodes(Collection $users, ?int $parentId): array
    {
        return $users->where('parent_id', $parentId)
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->map(function (User $user) use ($users) {
                $children = $this->buildNodes($users, $user->id);

                return [
                    'user' => $user,
                    'children' => $children,
                    'descendants_count' => collect($children)->sum(fn ($c) => 1 + $c['descendants_count']),
                ];
            })->values()->all();
    }

    public function isInSubtree(User $candidate, User $root): bool
    {
        return $this->subtreeUsers($root->id)->contains('id', $candidate->id);
    }

    /** Number of levels in $root's subtree, counting $root itself (leaf = 1). */
    public function subtreeHeight(User $root): int
    {
        return $this->subtreeUsers($root->id)->max('depth') - $root->depth + 1;
    }

    /**
     * Reassign the sponsor and re-cache depth for the whole moved subtree.
     * Validation (cycles, depth budget) is the caller's responsibility.
     */
    public function changeSponsor(User $seller, ?User $newParent): void
    {
        DB::transaction(function () use ($seller, $newParent) {
            $seller->parent_id = $newParent?->id;
            $seller->depth = $newParent ? $newParent->depth + 1 : 1;
            $seller->save();

            $this->recacheChildren($seller);
        });
    }

    protected function recacheChildren(User $parent): void
    {
        foreach ($parent->children()->get() as $child) {
            $child->depth = $parent->depth + 1;
            $child->save();
            $this->recacheChildren($child);
        }
    }

    /**
     * Approved-sales counts in the trailing activity window, as of now.
     * Used for the tree badges.
     *
     * @return array<int, int> seller_id => count
     */
    public function recentSalesCounts(Collection $users): array
    {
        return Sale::whereIn('seller_id', $users->pluck('id'))
            ->where('status', 'approved')
            ->where('sold_at', '>=', now()->subDays((int) config('commissions.activity_window_days')))
            ->selectRaw('seller_id, COUNT(*) as c')
            ->groupBy('seller_id')
            ->pluck('c', 'seller_id')
            ->all();
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=SellerTreeTest` then full suite.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(commissions): SellerTree service (subtree CTE, forest, move/recache, activity counts)"
```

---

## Task 4: CommissionDistributor (distribute, activity gate, refund)

**Files:**
- Create: `app/Services/CommissionDistributor.php`
- Test: `tests/Feature/CommissionDistributionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CommissionDistributionTest.php`:
```php
<?php

use App\Models\BonusPoolEntry;
use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function distributor(): CommissionDistributor
{
    return app(CommissionDistributor::class);
}

function makeChain(int $people): array
{
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < $people; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }

    return $users; // [0] is top-level; last is deepest
}

test('approving a sale writes seller payout, auto-level payouts and pool entries', function () {
    $admin = User::factory()->admin()->create();
    [$top, $mid, $seller] = makeChain(3); // seller has 2 uplines

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $sale->refresh();
    expect($sale->status)->toBe('approved');
    expect($sale->approved_by)->toBe($admin->id);

    $payouts = CommissionPayout::where('sale_id', $sale->id)->get();
    expect($payouts)->toHaveCount(3); // seller + L1 + L2

    expect($payouts->firstWhere('level', 0)->recipient_id)->toBe($seller->id);
    expect($payouts->firstWhere('level', 0)->amount_cents)->toBe(10_000);
    expect($payouts->firstWhere('level', 1)->recipient_id)->toBe($mid->id);
    expect($payouts->firstWhere('level', 1)->amount_cents)->toBe(5_000);
    expect($payouts->firstWhere('level', 2)->recipient_id)->toBe($top->id);

    // levels 3..9 have no upline -> pool, plus a rounding row
    $pool = BonusPoolEntry::where('sale_id', $sale->id)->get();
    expect($pool->where('reason', 'no_upline'))->toHaveCount(7);
    expect($pool->firstWhere('reason', 'no_upline')->level)->not->toBeNull();

    // invariant: everything reconciles to floor(100000*1023/5120) = 19980
    $total = $payouts->sum('amount_cents') + $pool->sum('amount_cents');
    expect($total)->toBe(19_980);
});

test('levels 4-9 only pay active uplines and the snapshot is stored', function () {
    $admin = User::factory()->admin()->create();
    $chain = makeChain(6); // seller at depth 6 has uplines L1..L5
    $seller = $chain[5];
    $l4 = $chain[1]; // level 4 upline
    $l5 = $chain[0]; // level 5 upline

    // Make ONLY the level-5 upline active (2 approved sales inside the window).
    Sale::factory()->approved()->count(2)->create([
        'seller_id' => $l5->id,
        'sold_at' => now()->subDays(10),
    ]);

    $sale = Sale::factory()->create([
        'seller_id' => $seller->id,
        'amount_cents' => 100_000,
        'sold_at' => now(),
    ]);
    distributor()->distribute($sale, $admin);

    $payouts = CommissionPayout::where('sale_id', $sale->id)->get();
    expect($payouts->firstWhere('level', 4))->toBeNull(); // inactive -> skipped
    expect($payouts->firstWhere('level', 5)->recipient_id)->toBe($l5->id);
    expect($payouts->firstWhere('level', 5)->recipient_was_active)->toBeTrue();

    $l4Entry = BonusPoolEntry::where('sale_id', $sale->id)->where('level', 4)->first();
    expect($l4Entry->reason)->toBe('inactive_upline');
    expect($l4Entry->amount_cents)->toBe(625);
});

test('the activity window is anchored at the sale sold_at, not at approval time', function () {
    $admin = User::factory()->admin()->create();
    $chain = makeChain(5);
    $seller = $chain[4];
    $l4 = $chain[0];

    // L4 upline has 2 approved sales ~100 days ago.
    Sale::factory()->approved()->count(2)->create([
        'seller_id' => $l4->id,
        'sold_at' => now()->subDays(100),
    ]);

    // A sale sold 95 days ago: window [185..95 days ago] includes those sales.
    $oldSale = Sale::factory()->create([
        'seller_id' => $seller->id, 'amount_cents' => 100_000, 'sold_at' => now()->subDays(95),
    ]);
    distributor()->distribute($oldSale, $admin);
    expect(CommissionPayout::where('sale_id', $oldSale->id)->where('level', 4)->exists())->toBeTrue();

    // A sale sold today: those sales are now outside the 90-day window.
    $newSale = Sale::factory()->create([
        'seller_id' => $seller->id, 'amount_cents' => 100_000, 'sold_at' => now(),
    ]);
    distributor()->distribute($newSale, $admin);
    expect(CommissionPayout::where('sale_id', $newSale->id)->where('level', 4)->exists())->toBeFalse();
});

test('refunding reverses payouts and offsets the pool', function () {
    $admin = User::factory()->admin()->create();
    [$top, $seller] = makeChain(2);

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    distributor()->distribute($sale, $admin);

    $originalPool = (int) BonusPoolEntry::where('sale_id', $sale->id)->sum('amount_cents');
    distributor()->refund($sale->refresh());

    $sale->refresh();
    expect($sale->status)->toBe('refunded');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('status', 'paid')->count())->toBe(0);

    expect((int) BonusPoolEntry::where('sale_id', $sale->id)->sum('amount_cents'))->toBe(0);
    expect(BonusPoolEntry::where('sale_id', $sale->id)->where('reason', 'refund_reversal')->first()->amount_cents)
        ->toBe(-$originalPool);
});

test('only pending sales can be distributed and only approved sales refunded', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->approved()->create();

    expect(fn () => distributor()->distribute($sale, $admin))->toThrow(LogicException::class);
    expect(fn () => distributor()->refund(Sale::factory()->create()))->toThrow(LogicException::class);
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=CommissionDistributionTest`
Expected: FAIL — `CommissionDistributor` not found.

- [ ] **Step 3: Implement `app/Services/CommissionDistributor.php`**

```php
<?php

namespace App\Services;

use App\Models\BonusPoolEntry;
use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Persists commission ledgers when a sale is approved. The payout rows are the
 * immutable snapshot of the chain (recipient, level, rate, active flag) at
 * distribution time; later sponsor changes never touch them.
 */
class CommissionDistributor
{
    public function __construct(private CommissionCalculator $calculator)
    {
    }

    public function distribute(Sale $sale, User $approver): void
    {
        if ($sale->status !== 'pending') {
            throw new \LogicException('Only pending sales can be approved.');
        }

        DB::transaction(function () use ($sale, $approver) {
            $chain = $sale->seller->uplineChain();

            $uplineSlots = [];
            foreach ($chain as $level => $upline) {
                $uplineSlots[$level] = $this->isActive($upline, $sale->sold_at);
            }

            $result = $this->calculator->calculate($sale->amount_cents, $uplineSlots);

            CommissionPayout::create([
                'sale_id' => $sale->id,
                'recipient_id' => $sale->seller_id,
                'level' => 0,
                'rate_numerator' => (int) config('commissions.seller_numerator'),
                'amount_cents' => $result['seller_cents'],
                'recipient_was_active' => true,
                'status' => 'paid',
            ]);

            $numerators = config('commissions.level_numerators');

            foreach ($result['levels'] as $level => $line) {
                if ($line['paid']) {
                    CommissionPayout::create([
                        'sale_id' => $sale->id,
                        'recipient_id' => $chain[$level]->id,
                        'level' => $level,
                        'rate_numerator' => $numerators[$level],
                        'amount_cents' => $line['amount_cents'],
                        'recipient_was_active' => $uplineSlots[$level],
                        'status' => 'paid',
                    ]);
                } elseif ($line['amount_cents'] > 0) {
                    BonusPoolEntry::create([
                        'sale_id' => $sale->id,
                        'level' => $level,
                        'amount_cents' => $line['amount_cents'],
                        'reason' => $line['pool_reason'],
                    ]);
                }
            }

            if ($result['pool_rounding_cents'] > 0) {
                BonusPoolEntry::create([
                    'sale_id' => $sale->id,
                    'level' => null,
                    'amount_cents' => $result['pool_rounding_cents'],
                    'reason' => 'rounding',
                ]);
            }

            $sale->status = 'approved';
            $sale->approved_by = $approver->id;
            $sale->approved_at = now();
            $sale->save();
        });
    }

    /**
     * Active = at least MIN_SALES_QUARTER approved sales with sold_at inside the
     * window ending at $at (the evaluated sale's sold_at — never recomputed later).
     */
    public function isActive(User $seller, Carbon $at): bool
    {
        $windowStart = $at->copy()->subDays((int) config('commissions.activity_window_days'));

        return Sale::where('seller_id', $seller->id)
            ->where('status', 'approved')
            ->whereBetween('sold_at', [$windowStart, $at])
            ->count() >= (int) config('commissions.min_sales_quarter');
    }

    public function refund(Sale $sale): void
    {
        if ($sale->status !== 'approved') {
            throw new \LogicException('Only approved sales can be refunded.');
        }

        DB::transaction(function () use ($sale) {
            $sale->payouts()->update(['status' => 'reversed']);

            $poolSum = (int) $sale->poolEntries()->sum('amount_cents');
            if ($poolSum > 0) {
                BonusPoolEntry::create([
                    'sale_id' => $sale->id,
                    'level' => null,
                    'amount_cents' => -$poolSum,
                    'reason' => 'refund_reversal',
                ]);
            }

            $sale->status = 'refunded';
            $sale->save();
        });
    }
}
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=CommissionDistributionTest` then full suite.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(commissions): CommissionDistributor with activity gate, snapshots and refund reversal"
```

---

## Task 5: Referral registration + depth enforcement

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/views/auth/register.blade.php`
- Test: `tests/Feature/ReferralRegistrationTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/ReferralRegistrationTest.php`:
```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'New Seller',
        'email' => 'new@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('registering through a referral link sets sponsor and depth', function () {
    $sponsor = User::factory()->approvedSeller()->create();

    $this->post('/register', registrationPayload(['ref' => $sponsor->referral_code]));

    $user = User::where('email', 'new@example.com')->first();
    expect($user->parent_id)->toBe($sponsor->id);
    expect($user->depth)->toBe($sponsor->depth + 1);
});

test('registering without a ref creates a top-level seller', function () {
    $this->post('/register', registrationPayload());

    $user = User::where('email', 'new@example.com')->first();
    expect($user->parent_id)->toBeNull();
    expect($user->depth)->toBe(1);
    expect($user->referral_code)->toHaveLength(8);
});

test('an invalid referral code is rejected', function () {
    $this->post('/register', registrationPayload(['ref' => 'NOPENOPE']))
        ->assertSessionHasErrors('ref');

    expect(User::where('email', 'new@example.com')->exists())->toBeFalse();
});

test('a non-approved sponsor code is rejected', function () {
    $pending = User::factory()->pending()->create();

    $this->post('/register', registrationPayload(['ref' => $pending->referral_code]))
        ->assertSessionHasErrors('ref');
});

test('a full chain (depth 10) rejects new signups', function () {
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < 10; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    expect($users[9]->depth)->toBe(10);

    $this->post('/register', registrationPayload(['ref' => $users[9]->referral_code]))
        ->assertSessionHasErrors('ref');
});

test('the register page shows the sponsor banner for a valid ref', function () {
    $sponsor = User::factory()->approvedSeller()->create(['name' => 'Sponsor Name']);

    $this->get('/register?ref='.$sponsor->referral_code)
        ->assertOk()
        ->assertSee('Sponsor Name');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=ReferralRegistrationTest`
Expected: FAIL — sponsor not set / no validation errors.

- [ ] **Step 3: Update `RegisteredUserController`**

In `app/Http/Controllers/Auth/RegisteredUserController.php`:

Replace `create()` with:
```php
public function create(Request $request): View
{
    $sponsor = null;

    if ($request->filled('ref')) {
        $sponsor = User::where('referral_code', $request->query('ref'))
            ->where('role', 'seller')->where('status', 'approved')->first();
    }

    return view('auth.register', [
        'sponsor' => $sponsor,
        'ref' => $request->query('ref'),
    ]);
}
```

In `store()`, add `'ref' => ['nullable', 'string'],` to the `$request->validate([...])` array, then insert the sponsor resolution between validation and `User::create`:
```php
$sponsor = null;

if ($request->filled('ref')) {
    $sponsor = User::where('referral_code', $request->input('ref'))
        ->where('role', 'seller')->where('status', 'approved')->first();

    if (! $sponsor) {
        throw ValidationException::withMessages(['ref' => __('messages.invalid_ref')]);
    }

    if ($sponsor->depth >= (int) config('commissions.max_depth')) {
        throw ValidationException::withMessages(['ref' => __('messages.chain_full')]);
    }
}
```
and after `$user = User::create([...]);` add:
```php
if ($sponsor) {
    $user->parent_id = $sponsor->id;
    $user->depth = $sponsor->depth + 1;
    $user->save();
}
```
Add `use Illuminate\Validation\ValidationException;` to the imports if not present.

- [ ] **Step 4: Update the register view**

In `resources/views/auth/register.blade.php`, right after the opening `<form ...>` tag add:
```blade
@if (($sponsor ?? null) !== null)
    <div class="mb-4 rounded-lg bg-blue-50 text-brand-blue px-4 py-3 text-sm">
        {{ __('messages.sponsored_by', ['name' => $sponsor->name]) }}
    </div>
@endif
<input type="hidden" name="ref" value="{{ old('ref', $ref ?? '') }}">
<x-input-error :messages="$errors->get('ref')" class="mb-4" />
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=ReferralRegistrationTest` then full suite (existing RegistrationTest must stay green — it posts without `ref`, which is nullable).
Expected: PASS. (The sponsor-banner test asserts the sponsor *name*, which renders regardless of the `messages.sponsored_by` key existing yet — the key itself arrives in Task 6.)

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat(commissions): referral-link registration with depth-10 enforcement"
```

---

## Task 6: i18n catalogs (EN + ES keys for everything ahead)

**Files:**
- Modify: `lang/en/messages.php`, `lang/es/messages.php`
- Test: `tests/Feature/LocaleParityTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/LocaleParityTest.php`:
```php
<?php

test('en and es message catalogs have identical keys', function () {
    $en = require lang_path('en/messages.php');
    $es = require lang_path('es/messages.php');

    expect(array_keys(array_diff_key($en, $es)))->toBe([]);
    expect(array_keys(array_diff_key($es, $en)))->toBe([]);
});

test('commission keys exist', function () {
    expect(__('messages.my_network'))->not->toBe('messages.my_network');
    expect(__('messages.calculator'))->not->toBe('messages.calculator');
    expect(__('messages.bonus_pool'))->not->toBe('messages.bonus_pool');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=LocaleParityTest`
Expected: the second test FAILS (keys missing).

- [ ] **Step 3: Append the new keys**

Append to the array in `lang/en/messages.php`:
```php
    // Network / tree
    'my_network' => 'My Network',
    'network' => 'Network',
    'your_sponsor' => 'Your sponsor',
    'top_level_seller' => 'You are a top-level seller',
    'downline' => 'Downline',
    'no_downline' => 'No one in your downline yet — share your referral link to start building your network.',
    'active' => 'Active',
    'inactive' => 'Inactive',
    'sales_90d' => 'sales (90d)',
    'members' => 'members',
    'sponsored_by' => 'Sponsored by :name',
    'invalid_ref' => 'This referral code is not valid.',
    'chain_full' => 'This referral chain is already at its maximum depth.',
    'referral_link' => 'Your referral link',
    'copy' => 'Copy',
    'copied' => 'Copied!',

    // Sales
    'my_sales' => 'My Sales',
    'all_sales' => 'Sales',
    'report_sale' => 'Report a sale',
    'sale_amount' => 'Sale amount (USD)',
    'sold_at_label' => 'Sale date',
    'notes' => 'Notes',
    'submit_sale' => 'Submit sale',
    'sale_submitted' => 'Sale submitted — pending admin approval.',
    'no_sales' => 'No sales yet.',
    'status_refunded' => 'Refunded',
    'filter_refunded' => 'Refunded',
    'refund' => 'Refund',
    'sale_approved' => 'Sale approved and commissions distributed.',
    'sale_rejected' => 'Sale rejected.',
    'sale_refunded' => 'Sale refunded and payouts reversed.',
    'submitted' => 'Submitted',
    'seller' => 'Seller',

    // Commissions
    'my_commissions' => 'My Commissions',
    'your_sale' => 'Your sale',
    'from_seller' => 'From',
    'rate' => 'Rate',
    'status_paid' => 'Paid',
    'status_reversed' => 'Reversed',
    'total_earned' => 'Total earned',
    'earned_30d' => 'Last 30 days',
    'pending_sales' => 'Pending sales',
    'recent_commissions' => 'Recent commissions',
    'no_commissions' => 'No commissions yet.',

    // Admin extras
    'sales_volume' => 'Approved sales volume',
    'commissions_paid' => 'Commissions paid',
    'pool_balance' => 'Bonus pool balance',
    'bonus_pool' => 'Bonus Pool',
    'pool_entries' => 'Pool entries',
    'reason' => 'Reason',
    'reason_no_upline' => 'No upline',
    'reason_inactive_upline' => 'Inactive upline',
    'reason_rounding' => 'Rounding',
    'reason_refund_reversal' => 'Refund reversal',
    'no_entries' => 'No entries yet.',
    'sponsor' => 'Sponsor',
    'change_sponsor' => 'Change sponsor',
    'current_sponsor' => 'Current sponsor',
    'new_sponsor_email' => 'New sponsor email (leave empty for top-level)',
    'sponsor_updated' => 'Sponsor updated.',
    'sponsor_invalid' => 'No approved seller with that email.',
    'sponsor_cycle' => 'Cannot move a seller under their own downline.',
    'save' => 'Save',
    'none' => 'None',

    // Calculator
    'calculator' => 'Calculator',
    'calculator_title' => 'Commission Calculator',
    'calculator_intro' => 'Simulate how a sale is split between the seller, the upline chain, and the bonus pool.',
    'uplines_count' => 'Uplines in the chain (0–9)',
    'active_levels_hint' => 'Levels 1–3 always pay. Toggle activity for levels 4–9.',
    'level_active' => 'Level :n active',
    'calculate' => 'Calculate',
    'results' => 'Results',
    'level' => 'Level',
    'destination' => 'Destination',
    'dest_paid' => 'Paid to upline',
    'dest_pool_no_upline' => 'To pool (no upline)',
    'dest_pool_inactive' => 'To pool (inactive)',
    'rounding_remainder' => 'Rounding remainder',
    'seller_cut' => 'Seller (10%)',
    'uplines_total' => 'Uplines paid',
    'pool_total' => 'To bonus pool',
    'company_cost' => 'Total commission cost',
    'invariant_ok' => 'Reconciled: seller + uplines + pool = total',
```

Append to `lang/es/messages.php`:
```php
    // Red / árbol
    'my_network' => 'Mi Red',
    'network' => 'Red',
    'your_sponsor' => 'Tu patrocinador',
    'top_level_seller' => 'Eres un vendedor de primer nivel',
    'downline' => 'Red descendente',
    'no_downline' => 'Aún no hay nadie en tu red — comparte tu enlace de referido para empezar a construirla.',
    'active' => 'Activo',
    'inactive' => 'Inactivo',
    'sales_90d' => 'ventas (90d)',
    'members' => 'miembros',
    'sponsored_by' => 'Patrocinado por :name',
    'invalid_ref' => 'Este código de referido no es válido.',
    'chain_full' => 'Esta cadena de referidos ya está en su profundidad máxima.',
    'referral_link' => 'Tu enlace de referido',
    'copy' => 'Copiar',
    'copied' => '¡Copiado!',

    // Ventas
    'my_sales' => 'Mis Ventas',
    'all_sales' => 'Ventas',
    'report_sale' => 'Reportar una venta',
    'sale_amount' => 'Monto de la venta (USD)',
    'sold_at_label' => 'Fecha de la venta',
    'notes' => 'Notas',
    'submit_sale' => 'Enviar venta',
    'sale_submitted' => 'Venta enviada — pendiente de aprobación del administrador.',
    'no_sales' => 'Aún no hay ventas.',
    'status_refunded' => 'Reembolsada',
    'filter_refunded' => 'Reembolsadas',
    'refund' => 'Reembolsar',
    'sale_approved' => 'Venta aprobada y comisiones distribuidas.',
    'sale_rejected' => 'Venta rechazada.',
    'sale_refunded' => 'Venta reembolsada y pagos revertidos.',
    'submitted' => 'Enviada',
    'seller' => 'Vendedor',

    // Comisiones
    'my_commissions' => 'Mis Comisiones',
    'your_sale' => 'Tu venta',
    'from_seller' => 'De',
    'rate' => 'Tasa',
    'status_paid' => 'Pagada',
    'status_reversed' => 'Revertida',
    'total_earned' => 'Total ganado',
    'earned_30d' => 'Últimos 30 días',
    'pending_sales' => 'Ventas pendientes',
    'recent_commissions' => 'Comisiones recientes',
    'no_commissions' => 'Aún no hay comisiones.',

    // Extras admin
    'sales_volume' => 'Volumen de ventas aprobadas',
    'commissions_paid' => 'Comisiones pagadas',
    'pool_balance' => 'Saldo del fondo de bonos',
    'bonus_pool' => 'Fondo de Bonos',
    'pool_entries' => 'Movimientos del fondo',
    'reason' => 'Motivo',
    'reason_no_upline' => 'Sin línea ascendente',
    'reason_inactive_upline' => 'Línea ascendente inactiva',
    'reason_rounding' => 'Redondeo',
    'reason_refund_reversal' => 'Reversión por reembolso',
    'no_entries' => 'Aún no hay movimientos.',
    'sponsor' => 'Patrocinador',
    'change_sponsor' => 'Cambiar patrocinador',
    'current_sponsor' => 'Patrocinador actual',
    'new_sponsor_email' => 'Correo del nuevo patrocinador (vacío para primer nivel)',
    'sponsor_updated' => 'Patrocinador actualizado.',
    'sponsor_invalid' => 'No hay un vendedor aprobado con ese correo.',
    'sponsor_cycle' => 'No se puede mover a un vendedor debajo de su propia red.',
    'save' => 'Guardar',
    'none' => 'Ninguno',

    // Calculadora
    'calculator' => 'Calculadora',
    'calculator_title' => 'Calculadora de Comisiones',
    'calculator_intro' => 'Simula cómo se reparte una venta entre el vendedor, la cadena ascendente y el fondo de bonos.',
    'uplines_count' => 'Niveles ascendentes en la cadena (0–9)',
    'active_levels_hint' => 'Los niveles 1–3 siempre cobran. Activa o desactiva los niveles 4–9.',
    'level_active' => 'Nivel :n activo',
    'calculate' => 'Calcular',
    'results' => 'Resultados',
    'level' => 'Nivel',
    'destination' => 'Destino',
    'dest_paid' => 'Pagado a la línea ascendente',
    'dest_pool_no_upline' => 'Al fondo (sin línea ascendente)',
    'dest_pool_inactive' => 'Al fondo (inactivo)',
    'rounding_remainder' => 'Resto de redondeo',
    'seller_cut' => 'Vendedor (10%)',
    'uplines_total' => 'Pagado a ascendentes',
    'pool_total' => 'Al fondo de bonos',
    'company_cost' => 'Costo total de comisiones',
    'invariant_ok' => 'Conciliado: vendedor + ascendentes + fondo = total',
```

- [ ] **Step 4: Run tests**

Run: `php artisan test --filter=LocaleParityTest` then full suite.
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add -A
git commit -m "feat(commissions): EN/ES message catalogs for network, sales, commissions, pool, calculator"
```

---

## Task 7: Seller sales page (submit + list)

**Files:**
- Create: `app/Http/Controllers/SellerSaleController.php`, `resources/views/seller/sales/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/SaleSubmissionTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SaleSubmissionTest.php`:
```php
<?php

use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('an approved seller can submit a sale that lands pending', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '1234.56',
        'sold_at' => now()->toDateString(),
        'notes' => 'Voice AI for a dental clinic',
    ])->assertRedirect();

    $sale = Sale::first();
    expect($sale->seller_id)->toBe($seller->id);
    expect($sale->amount_cents)->toBe(123_456);
    expect($sale->status)->toBe('pending');
});

test('a future sale date is rejected', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '100',
        'sold_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('sold_at');
});

test('pending sellers cannot reach the sales page', function () {
    $seller = User::factory()->pending()->create();

    $this->actingAs($seller)->get('/seller/sales')->assertRedirect(route('pending'));
    $this->actingAs($seller)->post('/seller/sales', [
        'amount' => '100', 'sold_at' => now()->toDateString(),
    ])->assertRedirect(route('pending'));
    expect(Sale::count())->toBe(0);
});

test('a seller only sees their own sales', function () {
    $a = User::factory()->approvedSeller()->create();
    $b = User::factory()->approvedSeller()->create();
    Sale::factory()->create(['seller_id' => $a->id, 'notes' => 'mine-note']);
    Sale::factory()->create(['seller_id' => $b->id, 'notes' => 'other-note']);

    $this->actingAs($a)->get('/seller/sales')
        ->assertOk()
        ->assertSee('mine-note')
        ->assertDontSee('other-note');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SaleSubmissionTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Create `app/Http/Controllers/SellerSaleController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\Request;

class SellerSaleController extends Controller
{
    public function index(Request $request)
    {
        $sales = $request->user()->sales()->latest('sold_at')->paginate(15);

        return view('seller.sales.index', compact('sales'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:1000000'],
            'sold_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $sale = new Sale([
            'amount_cents' => (int) round($data['amount'] * 100),
            'sold_at' => $data['sold_at'],
            'notes' => $data['notes'] ?? null,
        ]);
        $sale->seller_id = $request->user()->id; // not mass-assignable
        $sale->save();

        return back()->with('status', __('messages.sale_submitted'));
    }
}
```

- [ ] **Step 4: Create `resources/views/seller/sales/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_sales') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <h3 class="font-semibold text-brand-navy">{{ __('messages.report_sale') }}</h3>
                <form method="POST" action="{{ route('seller.sales.store') }}" class="mt-4 grid gap-4 sm:grid-cols-4 items-end">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount')" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="sold_at" :value="__('messages.sold_at_label')" />
                        <x-text-input id="sold_at" name="sold_at" type="date" max="{{ now()->toDateString() }}"
                                      class="mt-1 block w-full" :value="old('sold_at', now()->toDateString())" required />
                        <x-input-error :messages="$errors->get('sold_at')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="notes" :value="__('messages.notes')" />
                        <x-text-input id="notes" name="notes" type="text" class="mt-1 block w-full" :value="old('notes')" />
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                    <div>
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.submit_sale') }}
                        </button>
                    </div>
                </form>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.sold_at_label') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.notes') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3">{{ __('messages.submitted') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->sold_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ \Illuminate\Support\Number::currency($sale->amount_cents / 100) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->notes }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-amber-100 text-amber-700' => $sale->status === 'pending',
                                            'bg-green-100 text-green-700' => $sale->status === 'approved',
                                            'bg-red-100 text-red-700' => $sale->status === 'rejected',
                                            'bg-gray-200 text-gray-600' => $sale->status === 'refunded',
                                        ])">
                                        {{ __('messages.status_'.$sale->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $sale->created_at->format('Y-m-d') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_sales') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, inside the existing `Route::middleware(['auth', 'seller.approved'])->group(...)` add:
```php
Route::get('/seller/sales', [\App\Http\Controllers\SellerSaleController::class, 'index'])->name('seller.sales.index');
Route::post('/seller/sales', [\App\Http\Controllers\SellerSaleController::class, 'store'])->name('seller.sales.store');
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=SaleSubmissionTest` then full suite.
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(commissions): seller sales submission and listing"
```

---

## Task 8: Admin sales management (approve / reject / refund)

**Files:**
- Create: `app/Http/Controllers/Admin/AdminSaleController.php`, `resources/views/admin/sales/index.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AdminSalesTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/AdminSalesTest.php`:
```php
<?php

use App\Models\CommissionPayout;
use App\Models\Sale;
use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can list sales and filter by status', function () {
    $admin = User::factory()->admin()->create();
    Sale::factory()->create(['notes' => 'pending-note']);
    Sale::factory()->approved()->create(['notes' => 'approved-note']);

    $this->actingAs($admin)->get('/admin/sales')->assertOk()
        ->assertSee('pending-note')->assertSee('approved-note');

    $this->actingAs($admin)->get('/admin/sales?status=pending')->assertOk()
        ->assertSee('pending-note')->assertDontSee('approved-note');
});

test('admin approval distributes commissions', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create(['amount_cents' => 100_000]);

    $this->actingAs($admin)->patch(route('admin.sales.approve', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('approved');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('level', 0)->first()->amount_cents)->toBe(10_000);
});

test('admin can reject a pending sale without distribution', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create();

    $this->actingAs($admin)->patch(route('admin.sales.reject', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('rejected');
    expect(CommissionPayout::count())->toBe(0);
});

test('admin can refund an approved sale', function () {
    $admin = User::factory()->admin()->create();
    $sale = Sale::factory()->create(['amount_cents' => 100_000]);
    $this->actingAs($admin)->patch(route('admin.sales.approve', $sale));

    $this->actingAs($admin)->patch(route('admin.sales.refund', $sale))->assertRedirect();

    expect($sale->fresh()->status)->toBe('refunded');
    expect(CommissionPayout::where('sale_id', $sale->id)->where('status', 'paid')->count())->toBe(0);
});

test('approving a non-pending sale returns 404 and refunding a non-approved sale returns 404', function () {
    $admin = User::factory()->admin()->create();
    $approved = Sale::factory()->approved()->create();
    $pending = Sale::factory()->create();

    $this->actingAs($admin)->patch(route('admin.sales.approve', $approved))->assertNotFound();
    $this->actingAs($admin)->patch(route('admin.sales.refund', $pending))->assertNotFound();
});

test('non-admins cannot manage sales', function () {
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create();

    $this->actingAs($seller)->get('/admin/sales')->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.approve', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.reject', $sale))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sales.refund', $sale))->assertForbidden();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminSalesTest`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Create `app/Http/Controllers/Admin/AdminSaleController.php`**

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Services\CommissionDistributor;
use Illuminate\Http\Request;

class AdminSaleController extends Controller
{
    public function __construct(private CommissionDistributor $distributor)
    {
    }

    public function index(Request $request)
    {
        $status = $request->query('status');

        $sales = Sale::with('seller')
            ->when(in_array($status, ['pending', 'approved', 'rejected', 'refunded'], true),
                fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.sales.index', compact('sales', 'status'));
    }

    public function approve(Request $request, Sale $sale)
    {
        abort_unless($sale->status === 'pending', 404);

        $this->distributor->distribute($sale, $request->user());

        return back()->with('status', __('messages.sale_approved'));
    }

    public function reject(Sale $sale)
    {
        abort_unless($sale->status === 'pending', 404);

        $sale->status = 'rejected';
        $sale->save();

        return back()->with('status', __('messages.sale_rejected'));
    }

    public function refund(Sale $sale)
    {
        abort_unless($sale->status === 'approved', 404);

        $this->distributor->refund($sale);

        return back()->with('status', __('messages.sale_refunded'));
    }
}
```

- [ ] **Step 4: Create `resources/views/admin/sales/index.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.all_sales') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-green-50 text-green-800 px-4 py-3 text-sm">{{ session('status') }}</div>
            @endif

            <div class="mb-4 flex gap-2 text-sm">
                @foreach (['' => 'all', 'pending' => 'pending', 'approved' => 'approved', 'rejected' => 'rejected', 'refunded' => 'refunded'] as $value => $label)
                    <a href="{{ route('admin.sales.index', $value ? ['status' => $value] : []) }}"
                       class="px-3 py-1.5 rounded-full {{ ($status ?? '') === $value ? 'bg-brand-blue text-white' : 'bg-gray-100 text-gray-600' }}">
                        {{ __('messages.filter_'.$label) }}
                    </a>
                @endforeach
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sold_at_label') }}</th>
                            <th class="px-4 py-3">{{ __('messages.notes') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('messages.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($sales as $sale)
                            <tr>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ $sale->seller->name }}</td>
                                <td class="px-4 py-3">{{ \Illuminate\Support\Number::currency($sale->amount_cents / 100) }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $sale->sold_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ \Illuminate\Support\Str::limit($sale->notes, 40) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium
                                        @class([
                                            'bg-amber-100 text-amber-700' => $sale->status === 'pending',
                                            'bg-green-100 text-green-700' => $sale->status === 'approved',
                                            'bg-red-100 text-red-700' => $sale->status === 'rejected',
                                            'bg-gray-200 text-gray-600' => $sale->status === 'refunded',
                                        ])">
                                        {{ __('messages.status_'.$sale->status) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex justify-end gap-2">
                                        @if ($sale->status === 'pending')
                                            <form method="POST" action="{{ route('admin.sales.approve', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-brand-blue font-medium">{{ __('messages.approve') }}</button>
                                            </form>
                                            <form method="POST" action="{{ route('admin.sales.reject', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-red-600 font-medium">{{ __('messages.reject') }}</button>
                                            </form>
                                        @elseif ($sale->status === 'approved')
                                            <form method="POST" action="{{ route('admin.sales.refund', $sale) }}">
                                                @csrf @method('PATCH')
                                                <button class="text-red-600 font-medium">{{ __('messages.refund') }}</button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_sales') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">{{ $sales->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add routes**

In `routes/web.php`, inside the existing `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(...)` add:
```php
Route::get('/sales', [\App\Http\Controllers\Admin\AdminSaleController::class, 'index'])->name('sales.index');
Route::patch('/sales/{sale}/approve', [\App\Http\Controllers\Admin\AdminSaleController::class, 'approve'])->name('sales.approve');
Route::patch('/sales/{sale}/reject', [\App\Http\Controllers\Admin\AdminSaleController::class, 'reject'])->name('sales.reject');
Route::patch('/sales/{sale}/refund', [\App\Http\Controllers\Admin\AdminSaleController::class, 'refund'])->name('sales.refund');
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=AdminSalesTest` then full suite.
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(commissions): admin sales management with approve/reject/refund"
```

---

## Task 9: Network pages + tree component

**Files:**
- Create: `resources/views/partials/seller-tree-node.blade.php`, `app/Http/Controllers/SellerNetworkController.php`, `app/Http/Controllers/Admin/AdminNetworkController.php`, `resources/views/seller/network.blade.php`, `resources/views/admin/network.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/NetworkPagesTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/NetworkPagesTest.php`:
```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a seller sees their own downline and sponsor but not other branches', function () {
    $sponsor = User::factory()->approvedSeller()->create(['name' => 'Root Sponsor']);
    $me = User::factory()->approvedSeller()->withSponsor($sponsor)->create();
    $myChild = User::factory()->approvedSeller()->withSponsor($me)->create(['name' => 'My Recruit']);
    $sibling = User::factory()->approvedSeller()->withSponsor($sponsor)->create(['name' => 'Sibling Seller']);

    $this->actingAs($me)->get('/seller/network')
        ->assertOk()
        ->assertSee('My Recruit')
        ->assertSee('Root Sponsor')   // sponsor line only
        ->assertDontSee('Sibling Seller');
});

test('a top-level seller sees the top-level message', function () {
    $me = User::factory()->approvedSeller()->create();

    $this->actingAs($me)->get('/seller/network')
        ->assertOk()
        ->assertSee(__('messages.top_level_seller'));
});

test('admin network shows every root and branch', function () {
    $admin = User::factory()->admin()->create();
    $r1 = User::factory()->approvedSeller()->create(['name' => 'Root One']);
    User::factory()->approvedSeller()->withSponsor($r1)->create(['name' => 'Child One']);
    User::factory()->approvedSeller()->create(['name' => 'Root Two']);

    $this->actingAs($admin)->get('/admin/network')
        ->assertOk()
        ->assertSee('Root One')->assertSee('Child One')->assertSee('Root Two');
});

test('pending sellers and non-admins are gated', function () {
    $pending = User::factory()->pending()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($pending)->get('/seller/network')->assertRedirect(route('pending'));
    $this->actingAs($seller)->get('/admin/network')->assertForbidden();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=NetworkPagesTest`
Expected: FAIL — routes not defined.

- [ ] **Step 3: Create the tree node partial**

Create `resources/views/partials/seller-tree-node.blade.php`:
```blade
{{-- Params: $node (user/children/descendants_count), $counts (id => 90d sales), $rootDepth (int) --}}
@php
    $user = $node['user'];
    $sales90 = $counts[$user->id] ?? 0;
    $isActive = $sales90 >= (int) config('commissions.min_sales_quarter');
    $relLevel = $user->depth - $rootDepth;
@endphp
<div x-data="{ open: true }" class="mt-1">
    <div class="flex items-center gap-2 rounded-lg px-3 py-2 {{ $relLevel === 0 ? 'bg-blue-50' : 'bg-white border border-gray-100' }}">
        @if (count($node['children']) > 0)
            <button type="button" @click="open = !open" class="text-gray-400 w-4 text-left" x-text="open ? '▾' : '▸'"></button>
        @else
            <span class="w-4"></span>
        @endif
        <span class="w-7 h-7 rounded-full bg-brand-blue text-white flex items-center justify-center text-xs font-bold shrink-0">
            {{ strtoupper(mb_substr($user->name, 0, 1)) }}
        </span>
        <span class="font-medium text-brand-navy">{{ $user->name }}</span>
        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $isActive ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' }}">
            {{ $isActive ? __('messages.active') : __('messages.inactive') }}
        </span>
        <span class="ml-auto text-xs text-gray-500 whitespace-nowrap">
            L{{ $relLevel }} · {{ $sales90 }} {{ __('messages.sales_90d') }} · {{ $node['descendants_count'] }} {{ __('messages.downline') }}
        </span>
    </div>
    @if (count($node['children']) > 0)
        <div x-show="open" class="ml-5 pl-3 border-l-2 border-gray-100">
            @foreach ($node['children'] as $child)
                @include('partials.seller-tree-node', ['node' => $child, 'counts' => $counts, 'rootDepth' => $rootDepth])
            @endforeach
        </div>
    @endif
</div>
```

- [ ] **Step 4: Create the controllers**

`app/Http/Controllers/SellerNetworkController.php`:
```php
<?php

namespace App\Http\Controllers;

use App\Services\SellerTree;
use Illuminate\Http\Request;

class SellerNetworkController extends Controller
{
    public function __invoke(Request $request, SellerTree $tree)
    {
        $me = $request->user();
        $node = $tree->subtree($me);
        $counts = $tree->recentSalesCounts($tree->subtreeUsers($me->id));

        return view('seller.network', [
            'node' => $node,
            'counts' => $counts,
            'sponsor' => $me->parent,
        ]);
    }
}
```

`app/Http/Controllers/Admin/AdminNetworkController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\SellerTree;

class AdminNetworkController extends Controller
{
    public function __invoke(SellerTree $tree)
    {
        $forest = $tree->forest();
        $counts = $tree->recentSalesCounts(User::where('role', 'seller')->get());

        return view('admin.network', compact('forest', 'counts'));
    }
}
```

- [ ] **Step 5: Create the views**

`resources/views/seller/network.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_network') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6 flex flex-wrap items-center gap-x-8 gap-y-2 text-sm">
                <div>
                    <span class="text-gray-500">{{ __('messages.your_sponsor') }}:</span>
                    <span class="font-semibold text-brand-navy">{{ $sponsor?->name ?? __('messages.top_level_seller') }}</span>
                </div>
                <div>
                    <span class="text-gray-500">{{ __('messages.downline') }}:</span>
                    <span class="font-semibold text-brand-navy">{{ $node['descendants_count'] }} {{ __('messages.members') }}</span>
                </div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @if ($node['descendants_count'] === 0)
                    <p class="text-gray-400 text-sm">{{ __('messages.no_downline') }}</p>
                @endif
                @include('partials.seller-tree-node', ['node' => $node, 'counts' => $counts, 'rootDepth' => $node['user']->depth])
            </div>
        </div>
    </div>
</x-app-layout>
```

`resources/views/admin/network.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.network') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                @forelse ($forest as $node)
                    @include('partials.seller-tree-node', ['node' => $node, 'counts' => $counts, 'rootDepth' => $node['user']->depth])
                @empty
                    <p class="text-gray-400 text-sm">{{ __('messages.no_sellers') }}</p>
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add routes**

In `routes/web.php`: inside the seller group add
```php
Route::get('/seller/network', \App\Http\Controllers\SellerNetworkController::class)->name('seller.network');
```
and inside the admin group add
```php
Route::get('/network', \App\Http\Controllers\Admin\AdminNetworkController::class)->name('network');
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=NetworkPagesTest` then full suite.
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(commissions): network tree pages for sellers and admins"
```

---

## Task 10: Seller commissions page + seller dashboard earnings/referral

**Files:**
- Create: `app/Http/Controllers/SellerCommissionController.php`, `resources/views/seller/commissions.blade.php`
- Modify: `app/Http/Controllers/SellerDashboardController.php`, `resources/views/seller/dashboard.blade.php`, `routes/web.php`
- Test: `tests/Feature/CommissionPagesTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CommissionPagesTest.php`:
```php
<?php

use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a seller sees only their own payouts', function () {
    $admin = User::factory()->admin()->create();
    $sponsor = User::factory()->approvedSeller()->create();
    $seller = User::factory()->approvedSeller()->withSponsor($sponsor)->create(['name' => 'Closer Carl']);

    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    // Sponsor sees their L1 payout, sourced from Carl's sale
    $this->actingAs($sponsor)->get('/seller/commissions')
        ->assertOk()->assertSee('Closer Carl')->assertSee('50.00');

    // Carl sees his own level-0 payout but not the sponsor's view of it
    $this->actingAs($seller)->get('/seller/commissions')
        ->assertOk()->assertSee('100.00');
});

test('the seller dashboard shows earnings cards and the referral link', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 50_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    $this->actingAs($seller)->get('/seller/dashboard')
        ->assertOk()
        ->assertSee(__('messages.total_earned'))
        ->assertSee('50.00')                       // 10% of $500
        ->assertSee($seller->referral_code);
});

test('reversed payouts are excluded from earnings totals', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 50_000]);
    $distributor = app(CommissionDistributor::class);
    $distributor->distribute($sale, $admin);
    $distributor->refund($sale->refresh());

    $this->actingAs($seller)->get('/seller/dashboard')
        ->assertOk()
        ->assertSee('0.00');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=CommissionPagesTest`
Expected: FAIL — `/seller/commissions` route missing.

- [ ] **Step 3: Create `app/Http/Controllers/SellerCommissionController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\CommissionPayout;
use Illuminate\Http\Request;

class SellerCommissionController extends Controller
{
    public function __invoke(Request $request)
    {
        $payouts = CommissionPayout::with('sale.seller')
            ->where('recipient_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        $totalCents = (int) CommissionPayout::where('recipient_id', $request->user()->id)
            ->where('status', 'paid')->sum('amount_cents');

        return view('seller.commissions', compact('payouts', 'totalCents'));
    }
}
```

- [ ] **Step 4: Create `resources/views/seller/commissions.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.my_commissions') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-gray-500">{{ __('messages.total_earned') }}</div>
                <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($totalCents / 100) }}</div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.registered') }}</th>
                            <th class="px-4 py-3">{{ __('messages.level') }}</th>
                            <th class="px-4 py-3">{{ __('messages.from_seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.rate') }}</th>
                            <th class="px-4 py-3">{{ __('messages.sale_amount') }}</th>
                            <th class="px-4 py-3">{{ __('messages.status') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($payouts as $payout)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $payout->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3">{{ $payout->level === 0 ? __('messages.your_sale') : 'L'.$payout->level }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $payout->sale->seller->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ rtrim(rtrim(number_format($payout->rate_numerator / 5120 * 100, 8), '0'), '.') }}%</td>
                                <td class="px-4 py-3 font-medium text-brand-navy">{{ \Illuminate\Support\Number::currency($payout->amount_cents / 100) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $payout->status === 'paid' ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-600' }}">
                                        {{ __('messages.status_'.$payout->status) }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_commissions') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $payouts->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Extend the seller dashboard**

Replace `app/Http/Controllers/SellerDashboardController.php` content of `__invoke` with:
```php
public function __invoke(Request $request)
{
    $user = $request->user();

    $paid = $user->commissionPayouts()->where('status', 'paid');

    return view('seller.dashboard', [
        'user' => $user,
        'totalEarnedCents' => (int) (clone $paid)->sum('amount_cents'),
        'earned30Cents' => (int) (clone $paid)->where('created_at', '>=', now()->subDays(30))->sum('amount_cents'),
        'pendingSalesCount' => $user->sales()->where('status', 'pending')->count(),
        'recentPayouts' => $user->commissionPayouts()->with('sale.seller')->latest()->limit(5)->get(),
        'referralLink' => $user->referralLink(),
    ]);
}
```

In `resources/views/seller/dashboard.blade.php`, after the welcome card (`{{ __('messages.welcome_name'...) }}` block's closing `</div>`), insert:
```blade
<div class="grid gap-6 sm:grid-cols-3">
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-sm text-gray-500">{{ __('messages.total_earned') }}</div>
        <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($totalEarnedCents / 100) }}</div>
    </div>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-sm text-gray-500">{{ __('messages.earned_30d') }}</div>
        <div class="text-3xl font-bold text-brand-blue">{{ \Illuminate\Support\Number::currency($earned30Cents / 100) }}</div>
    </div>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-sm text-gray-500">{{ __('messages.pending_sales') }}</div>
        <div class="text-3xl font-bold text-amber-500">{{ $pendingSalesCount }}</div>
    </div>
</div>

<div class="bg-white shadow-sm sm:rounded-lg p-6" x-data="{ copied: false }">
    <h4 class="font-semibold text-brand-navy">{{ __('messages.referral_link') }}</h4>
    <div class="mt-3 flex gap-2">
        <input type="text" readonly value="{{ $referralLink }}"
               class="flex-1 rounded-lg border-gray-300 text-sm text-gray-600 bg-gray-50">
        <button type="button"
                @click="navigator.clipboard.writeText('{{ $referralLink }}'); copied = true; setTimeout(() => copied = false, 1500)"
                class="bg-brand-blue hover:bg-blue-700 text-white text-sm font-semibold px-4 py-2 rounded-lg">
            <span x-show="!copied">{{ __('messages.copy') }}</span>
            <span x-show="copied" x-cloak>{{ __('messages.copied') }}</span>
        </button>
    </div>
</div>

<div class="bg-white shadow-sm sm:rounded-lg p-6">
    <h4 class="font-semibold text-brand-navy">{{ __('messages.recent_commissions') }}</h4>
    <ul class="mt-3 divide-y divide-gray-100 text-sm">
        @forelse ($recentPayouts as $payout)
            <li class="py-2 flex justify-between">
                <span class="text-gray-600">
                    {{ $payout->level === 0 ? __('messages.your_sale') : 'L'.$payout->level.' · '.$payout->sale->seller->name }}
                </span>
                <span class="font-medium {{ $payout->status === 'paid' ? 'text-brand-navy' : 'text-gray-400 line-through' }}">
                    {{ \Illuminate\Support\Number::currency($payout->amount_cents / 100) }}
                </span>
            </li>
        @empty
            <li class="py-2 text-gray-400">{{ __('messages.no_commissions') }}</li>
        @endforelse
    </ul>
</div>
```

- [ ] **Step 6: Add the route**

Inside the seller group in `routes/web.php`:
```php
Route::get('/seller/commissions', \App\Http\Controllers\SellerCommissionController::class)->name('seller.commissions');
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=CommissionPagesTest` then full suite (the existing seller-dashboard test must stay green — the view still renders `$user->name`).
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(commissions): seller commissions page, earnings cards and referral link"
```

---

## Task 11: Admin dashboard cards + bonus pool page

**Files:**
- Create: `app/Http/Controllers/Admin/AdminBonusPoolController.php`, `resources/views/admin/bonus-pool.blade.php`
- Modify: `app/Http/Controllers/Admin/AdminDashboardController.php`, `resources/views/admin/dashboard.blade.php`, `routes/web.php`
- Test: `tests/Feature/CommissionPagesTest.php` (append)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/CommissionPagesTest.php`:
```php
test('the admin dashboard shows sales and pool stats', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(); // top-level: most upline cents -> pool
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    $this->actingAs($admin)->get('/admin/dashboard')
        ->assertOk()
        ->assertSee(__('messages.pool_balance'))
        ->assertSee('99.80'); // pool = 19980 - 10000 seller = 9980 cents
});

test('the bonus pool page lists entries and balance, admin only', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $sale = Sale::factory()->create(['seller_id' => $seller->id, 'amount_cents' => 100_000]);
    app(CommissionDistributor::class)->distribute($sale, $admin);

    $this->actingAs($admin)->get('/admin/bonus-pool')
        ->assertOk()
        ->assertSee(__('messages.reason_no_upline'));

    $this->actingAs($seller)->get('/admin/bonus-pool')->assertForbidden();
});
```
Add `use App\Services\CommissionDistributor;` is already imported in this file from Task 10.

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=CommissionPagesTest`
Expected: the two new tests FAIL.

- [ ] **Step 3: Extend `AdminDashboardController`**

Replace the `index()` body in `app/Http/Controllers/Admin/AdminDashboardController.php` with:
```php
public function index()
{
    $stats = [
        'total' => User::where('role', 'seller')->count(),
        'pending' => User::where('role', 'seller')->where('status', 'pending')->count(),
        'approved' => User::where('role', 'seller')->where('status', 'approved')->count(),
        'rejected' => User::where('role', 'seller')->where('status', 'rejected')->count(),
    ];

    $salesStats = [
        'pending_sales' => \App\Models\Sale::where('status', 'pending')->count(),
        'volume_cents' => (int) \App\Models\Sale::where('status', 'approved')->sum('amount_cents'),
        'paid_cents' => (int) \App\Models\CommissionPayout::where('status', 'paid')->sum('amount_cents'),
        'pool_cents' => (int) \App\Models\BonusPoolEntry::sum('amount_cents'),
    ];

    return view('admin.dashboard', compact('stats', 'salesStats'));
}
```

- [ ] **Step 4: Add the second card row to `resources/views/admin/dashboard.blade.php`**

After the existing seller-stats grid `</div>`, insert:
```blade
<div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-3xl font-bold text-amber-500">{{ $salesStats['pending_sales'] }}</div>
        <div class="mt-1 text-sm text-gray-500">{{ __('messages.pending_sales') }}</div>
    </div>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-3xl font-bold text-brand-navy">{{ \Illuminate\Support\Number::currency($salesStats['volume_cents'] / 100) }}</div>
        <div class="mt-1 text-sm text-gray-500">{{ __('messages.sales_volume') }}</div>
    </div>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-3xl font-bold text-brand-blue">{{ \Illuminate\Support\Number::currency($salesStats['paid_cents'] / 100) }}</div>
        <div class="mt-1 text-sm text-gray-500">{{ __('messages.commissions_paid') }}</div>
    </div>
    <div class="bg-white shadow-sm sm:rounded-lg p-6">
        <div class="text-3xl font-bold text-emerald-600">{{ \Illuminate\Support\Number::currency($salesStats['pool_cents'] / 100) }}</div>
        <div class="mt-1 text-sm text-gray-500">{{ __('messages.pool_balance') }}</div>
    </div>
</div>
```

- [ ] **Step 5: Create the bonus pool controller + view**

`app/Http/Controllers/Admin/AdminBonusPoolController.php`:
```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BonusPoolEntry;

class AdminBonusPoolController extends Controller
{
    public function __invoke()
    {
        return view('admin.bonus-pool', [
            'balanceCents' => (int) BonusPoolEntry::sum('amount_cents'),
            'entries' => BonusPoolEntry::with('sale.seller')->latest()->paginate(20),
        ]);
    }
}
```

`resources/views/admin/bonus-pool.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.bonus_pool') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-brand-navy text-white shadow-sm sm:rounded-lg p-6">
                <div class="text-sm text-blue-100/80">{{ __('messages.pool_balance') }}</div>
                <div class="text-4xl font-extrabold">{{ \Illuminate\Support\Number::currency($balanceCents / 100) }}</div>
            </div>

            <div class="bg-white shadow-sm sm:rounded-lg overflow-hidden">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-left text-gray-500">
                        <tr>
                            <th class="px-4 py-3">{{ __('messages.registered') }}</th>
                            <th class="px-4 py-3">{{ __('messages.seller') }}</th>
                            <th class="px-4 py-3">{{ __('messages.level') }}</th>
                            <th class="px-4 py-3">{{ __('messages.reason') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('messages.sale_amount') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->created_at->format('Y-m-d') }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->sale->seller->name }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ $entry->level !== null ? 'L'.$entry->level : '—' }}</td>
                                <td class="px-4 py-3 text-gray-600">{{ __('messages.reason_'.$entry->reason) }}</td>
                                <td class="px-4 py-3 text-right font-medium {{ $entry->amount_cents < 0 ? 'text-red-600' : 'text-brand-navy' }}">
                                    {{ \Illuminate\Support\Number::currency($entry->amount_cents / 100) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-gray-400">{{ __('messages.no_entries') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div>{{ $entries->links() }}</div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 6: Add the route**

Inside the admin group in `routes/web.php`:
```php
Route::get('/bonus-pool', \App\Http\Controllers\Admin\AdminBonusPoolController::class)->name('bonus-pool');
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=CommissionPagesTest` then full suite (existing admin-dashboard tests must stay green).
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(commissions): admin sales/pool dashboard cards and bonus pool ledger page"
```

---

## Task 12: Admin change-sponsor

**Files:**
- Create: `resources/views/admin/sellers/sponsor.blade.php`
- Modify: `app/Http/Controllers/Admin/AdminSellerController.php`, `resources/views/admin/sellers/index.blade.php`, `routes/web.php`
- Test: `tests/Feature/SponsorChangeTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/SponsorChangeTest.php`:
```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admin can change a seller sponsor', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $newSponsor = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $newSponsor->email])
        ->assertRedirect();

    $seller->refresh();
    expect($seller->parent_id)->toBe($newSponsor->id);
    expect($seller->depth)->toBe(2);
});

test('admin can make a seller top-level', function () {
    $admin = User::factory()->admin()->create();
    $sponsor = User::factory()->approvedSeller()->create();
    $seller = User::factory()->approvedSeller()->withSponsor($sponsor)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => ''])
        ->assertRedirect();

    $seller->refresh();
    expect($seller->parent_id)->toBeNull();
    expect($seller->depth)->toBe(1);
});

test('cycles are rejected', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $child = User::factory()->approvedSeller()->withSponsor($seller)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $child->email])
        ->assertSessionHasErrors('sponsor_email');

    expect($seller->fresh()->parent_id)->toBeNull();
});

test('moves that would exceed depth 10 are rejected', function () {
    $admin = User::factory()->admin()->create();
    $users = [User::factory()->approvedSeller()->create()];
    for ($i = 1; $i < 9; $i++) {
        $users[] = User::factory()->approvedSeller()->withSponsor($users[$i - 1])->create();
    }
    // users[8] is at depth 9; a seller with a child (height 2) cannot move under it
    $seller = User::factory()->approvedSeller()->create();
    User::factory()->approvedSeller()->withSponsor($seller)->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $users[8]->email])
        ->assertSessionHasErrors('sponsor_email');
});

test('an unknown or non-approved sponsor email is rejected', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();
    $pending = User::factory()->pending()->create();

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => 'ghost@nowhere.com'])
        ->assertSessionHasErrors('sponsor_email');

    $this->actingAs($admin)
        ->patch(route('admin.sellers.sponsor.update', $seller), ['sponsor_email' => $pending->email])
        ->assertSessionHasErrors('sponsor_email');
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=SponsorChangeTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Extend `AdminSellerController`**

Add to `app/Http/Controllers/Admin/AdminSellerController.php` (with imports `use App\Services\SellerTree;` and `use Illuminate\Validation\ValidationException;`):
```php
public function editSponsor(User $user)
{
    abort_unless($user->isSeller(), 404);

    return view('admin.sellers.sponsor', ['seller' => $user]);
}

public function updateSponsor(Request $request, User $user, SellerTree $tree)
{
    abort_unless($user->isSeller(), 404);

    $data = $request->validate(['sponsor_email' => ['nullable', 'email']]);

    if (empty($data['sponsor_email'])) {
        $tree->changeSponsor($user, null);

        return redirect()->route('admin.sellers.index')->with('status', __('messages.sponsor_updated'));
    }

    $newParent = User::where('email', $data['sponsor_email'])
        ->where('role', 'seller')->where('status', 'approved')->first();

    if (! $newParent) {
        throw ValidationException::withMessages(['sponsor_email' => __('messages.sponsor_invalid')]);
    }

    if ($newParent->id === $user->id || $tree->isInSubtree($newParent, $user)) {
        throw ValidationException::withMessages(['sponsor_email' => __('messages.sponsor_cycle')]);
    }

    if ($newParent->depth + $tree->subtreeHeight($user) > (int) config('commissions.max_depth')) {
        throw ValidationException::withMessages(['sponsor_email' => __('messages.chain_full')]);
    }

    $tree->changeSponsor($user, $newParent);

    return redirect()->route('admin.sellers.index')->with('status', __('messages.sponsor_updated'));
}
```

- [ ] **Step 4: Create `resources/views/admin/sellers/sponsor.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.change_sponsor') }} — {{ $seller->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-600">
                    {{ __('messages.current_sponsor') }}:
                    <span class="font-semibold text-brand-navy">{{ $seller->parent?->name ?? __('messages.none') }}</span>
                </p>

                <form method="POST" action="{{ route('admin.sellers.sponsor.update', $seller) }}" class="mt-6 space-y-4">
                    @csrf @method('PATCH')
                    <div>
                        <x-input-label for="sponsor_email" :value="__('messages.new_sponsor_email')" />
                        <x-text-input id="sponsor_email" name="sponsor_email" type="email" class="mt-1 block w-full"
                                      :value="old('sponsor_email', $seller->parent?->email)" />
                        <x-input-error :messages="$errors->get('sponsor_email')" class="mt-2" />
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                        {{ __('messages.save') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add sponsor column + link to the sellers table**

In `resources/views/admin/sellers/index.blade.php`: add a header cell after the Phone column:
```blade
<th class="px-4 py-3">{{ __('messages.sponsor') }}</th>
```
and the matching body cell after the phone cell:
```blade
<td class="px-4 py-3 text-gray-600">
    {{ $seller->parent?->name ?? '—' }}
    <a href="{{ route('admin.sellers.sponsor.edit', $seller) }}" class="text-brand-blue underline ml-1 text-xs">{{ __('messages.change_sponsor') }}</a>
</td>
```
Update the `@empty` row's `colspan` from 6 to 7. In `AdminSellerController::index`, change the query to eager-load: `User::with('parent')->where('role', 'seller')...`.

- [ ] **Step 6: Add routes**

Inside the admin group in `routes/web.php`:
```php
Route::get('/sellers/{user}/sponsor', [\App\Http\Controllers\Admin\AdminSellerController::class, 'editSponsor'])->name('sellers.sponsor.edit');
Route::patch('/sellers/{user}/sponsor', [\App\Http\Controllers\Admin\AdminSellerController::class, 'updateSponsor'])->name('sellers.sponsor.update');
```

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=SponsorChangeTest` then full suite.
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(commissions): admin change-sponsor with cycle and depth validation"
```

---

## Task 13: Calculator page

**Files:**
- Create: `app/Http/Controllers/CalculatorController.php`, `resources/views/calculator.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CalculatorTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/CalculatorTest.php`:
```php
<?php

use App\Models\User;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('admins and approved sellers can open the calculator', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/calculator')->assertOk();
    $this->actingAs(User::factory()->approvedSeller()->create())->get('/calculator')->assertOk();
});

test('pending sellers and guests cannot use the calculator', function () {
    $this->get('/calculator')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->pending()->create())->get('/calculator')->assertForbidden();
    $this->actingAs(User::factory()->pending()->create())
        ->post('/calculator', ['amount' => 100, 'uplines' => 3])->assertForbidden();
});

test('the calculator computes a full active chain for $1000', function () {
    $seller = User::factory()->approvedSeller()->create();

    $response = $this->actingAs($seller)->post('/calculator', [
        'amount' => '1000',
        'uplines' => 9,
        'active' => [4 => '1', 5 => '1', 6 => '1', 7 => '1', 8 => '1', 9 => '1'],
    ]);

    $response->assertOk()
        ->assertSee('100.00')   // seller cut $100.00
        ->assertSee('50.00')    // L1 $50.00
        ->assertSee('199.80');  // total charge $199.80
});

test('inactive levels route to the pool in the results', function () {
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->post('/calculator', [
        'amount' => '1000',
        'uplines' => 9,
        'active' => [], // levels 4-9 all inactive
    ])->assertOk()
      ->assertSee(__('messages.dest_pool_inactive'));
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=CalculatorTest`
Expected: FAIL — route not defined.

- [ ] **Step 3: Create `app/Http/Controllers/CalculatorController.php`**

```php
<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CommissionCalculator;
use Illuminate\Http\Request;

class CalculatorController extends Controller
{
    public function show(Request $request)
    {
        $this->authorizeAccess($request->user());

        return view('calculator', [
            'result' => null,
            'input' => ['amount' => 1000, 'uplines' => 9, 'active' => [4 => true, 5 => true, 6 => true, 7 => true, 8 => true, 9 => true]],
        ]);
    }

    public function compute(Request $request, CommissionCalculator $calculator)
    {
        $this->authorizeAccess($request->user());

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000000'],
            'uplines' => ['required', 'integer', 'min:0', 'max:9'],
            'active' => ['sometimes', 'array'],
            'active.*' => ['boolean'],
        ]);

        $autoLevels = (int) config('commissions.auto_levels');
        $slots = [];
        for ($level = 1; $level <= (int) $data['uplines']; $level++) {
            $slots[$level] = $level <= $autoLevels || (bool) ($data['active'][$level] ?? false);
        }

        $result = $calculator->calculate((int) round($data['amount'] * 100), $slots);

        return view('calculator', [
            'result' => $result,
            'input' => [
                'amount' => $data['amount'],
                'uplines' => (int) $data['uplines'],
                'active' => collect(range(4, 9))->mapWithKeys(fn ($l) => [$l => (bool) ($data['active'][$l] ?? false)])->all(),
            ],
        ]);
    }

    private function authorizeAccess(User $user): void
    {
        abort_unless($user->isAdmin() || ($user->isSeller() && $user->isApproved()), 403);
    }
}
```

- [ ] **Step 4: Create `resources/views/calculator.blade.php`**

```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.calculator_title') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-2">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <p class="text-sm text-gray-500">{{ __('messages.calculator_intro') }}</p>

                <form method="POST" action="{{ route('calculator.compute') }}" class="mt-6 space-y-5">
                    @csrf
                    <div>
                        <x-input-label for="amount" :value="__('messages.sale_amount')" />
                        <x-text-input id="amount" name="amount" type="number" step="0.01" min="0.01"
                                      class="mt-1 block w-full" :value="old('amount', $input['amount'])" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="uplines" :value="__('messages.uplines_count')" />
                        <select id="uplines" name="uplines" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            @foreach (range(0, 9) as $n)
                                <option value="{{ $n }}" @selected((int) old('uplines', $input['uplines']) === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500 mb-2">{{ __('messages.active_levels_hint') }}</p>
                        <div class="grid grid-cols-3 gap-2">
                            @foreach (range(4, 9) as $level)
                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" name="active[{{ $level }}]" value="0">
                                    <input type="checkbox" name="active[{{ $level }}]" value="1"
                                           class="rounded border-gray-300 text-brand-blue"
                                           @checked(old("active.$level", $input['active'][$level] ?? false))>
                                    {{ __('messages.level_active', ['n' => $level]) }}
                                </label>
                            @endforeach
                        </div>
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
                    @php $uplinesPaid = collect($result['levels'])->where('paid', true)->sum('amount_cents'); @endphp

                    <table class="mt-4 min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="text-left text-gray-500">
                            <tr>
                                <th class="py-2 pr-4">{{ __('messages.level') }}</th>
                                <th class="py-2 pr-4">{{ __('messages.rate') }}</th>
                                <th class="py-2 pr-4 text-right">{{ __('messages.sale_amount') }}</th>
                                <th class="py-2">{{ __('messages.destination') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr>
                                <td class="py-2 pr-4 font-medium text-brand-navy">{{ __('messages.seller_cut') }}</td>
                                <td class="py-2 pr-4">10%</td>
                                <td class="py-2 pr-4 text-right font-medium">{{ \Illuminate\Support\Number::currency($result['seller_cents'] / 100) }}</td>
                                <td class="py-2 text-green-700">{{ __('messages.dest_paid') }}</td>
                            </tr>
                            @foreach ($result['levels'] as $level => $line)
                                <tr class="{{ $line['exists'] ? '' : 'text-gray-400' }}">
                                    <td class="py-2 pr-4">L{{ $level }}</td>
                                    <td class="py-2 pr-4">{{ rtrim(rtrim(number_format(config('commissions.level_numerators')[$level] / 5120 * 100, 8), '0'), '.') }}%</td>
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
                        <dt class="text-gray-500 border-t pt-2">{{ __('messages.company_cost') }} (19.98046875%)</dt>
                        <dd class="text-right font-bold text-brand-navy border-t pt-2">{{ \Illuminate\Support\Number::currency($result['total_charge_cents'] / 100) }}</dd>
                    </dl>

                    <p class="mt-4 text-xs text-green-700">✓ {{ __('messages.invariant_ok') }}</p>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add routes**

In `routes/web.php` (outside the seller/admin groups — access is checked in the controller because admins are not sellers):
```php
Route::middleware('auth')->group(function () {
    Route::get('/calculator', [\App\Http\Controllers\CalculatorController::class, 'show'])->name('calculator');
    Route::post('/calculator', [\App\Http\Controllers\CalculatorController::class, 'compute'])->name('calculator.compute');
});
```

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=CalculatorTest` then full suite.
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(commissions): interactive commission calculator page"
```

---

## Task 14: Navigation, demo seeder, final verification

**Files:**
- Create: `database/seeders/CommissionDemoSeeder.php`
- Modify: `resources/views/layouts/navigation.blade.php`, `database/seeders/DatabaseSeeder.php`

- [ ] **Step 1: Role-aware navigation**

In `resources/views/layouts/navigation.blade.php`, replace the desktop nav-links block (the `<div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">` containing the single Dashboard link) with:
```blade
<div class="hidden space-x-6 sm:-my-px sm:ms-10 sm:flex">
    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard', 'admin.dashboard', 'seller.dashboard')">
        {{ __('Dashboard') }}
    </x-nav-link>
    @if (Auth::user()->isAdmin())
        <x-nav-link :href="route('admin.sellers.index')" :active="request()->routeIs('admin.sellers.*')">{{ __('messages.manage_sellers') }}</x-nav-link>
        <x-nav-link :href="route('admin.network')" :active="request()->routeIs('admin.network')">{{ __('messages.network') }}</x-nav-link>
        <x-nav-link :href="route('admin.sales.index')" :active="request()->routeIs('admin.sales.*')">{{ __('messages.all_sales') }}</x-nav-link>
        <x-nav-link :href="route('admin.bonus-pool')" :active="request()->routeIs('admin.bonus-pool')">{{ __('messages.bonus_pool') }}</x-nav-link>
        <x-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-nav-link>
    @elseif (Auth::user()->isSeller() && Auth::user()->isApproved())
        <x-nav-link :href="route('seller.network')" :active="request()->routeIs('seller.network')">{{ __('messages.my_network') }}</x-nav-link>
        <x-nav-link :href="route('seller.sales.index')" :active="request()->routeIs('seller.sales.*')">{{ __('messages.my_sales') }}</x-nav-link>
        <x-nav-link :href="route('seller.commissions')" :active="request()->routeIs('seller.commissions')">{{ __('messages.my_commissions') }}</x-nav-link>
        <x-nav-link :href="route('calculator')" :active="request()->routeIs('calculator')">{{ __('messages.calculator') }}</x-nav-link>
    @endif
</div>
```
Mirror the same links in the responsive menu section (the `<div class="pt-2 pb-3 space-y-1">` block) using `<x-responsive-nav-link>` with identical hrefs/labels.

- [ ] **Step 2: Create `database/seeders/CommissionDemoSeeder.php`**

```php
<?php

namespace Database\Seeders;

use App\Models\Sale;
use App\Models\User;
use App\Services\CommissionDistributor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CommissionDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (User::where('email', 'demo.ana@voicecentra.com')->exists()) {
            return; // already seeded
        }

        $admin = User::where('role', 'admin')->firstOrFail();
        $seller1 = User::where('email', 'seller1@seller1.com')->first();

        if (! $seller1) {
            return; // base seeder hasn't run
        }

        $make = function (string $name, string $email, User $sponsor): User {
            $user = User::factory()->approvedSeller()->withSponsor($sponsor)->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('seller'),
                'phone' => '555-0'.fake()->numberBetween(200, 999),
            ]);

            return $user;
        };

        // 3 levels under seller1
        $ana = $make('Ana Demo', 'demo.ana@voicecentra.com', $seller1);
        $bruno = $make('Bruno Demo', 'demo.bruno@voicecentra.com', $ana);
        $carla = $make('Carla Demo', 'demo.carla@voicecentra.com', $bruno);
        $make('Diego Demo', 'demo.diego@voicecentra.com', $ana);

        $distributor = app(CommissionDistributor::class);

        $submit = function (User $seller, int $cents, int $daysAgo) {
            $sale = new Sale(['amount_cents' => $cents, 'sold_at' => now()->subDays($daysAgo), 'notes' => 'Demo sale']);
            $sale->seller_id = $seller->id;
            $sale->save();

            return $sale;
        };

        // Approved sales flowing commissions up the chain
        foreach ([
            [$carla, 120_000, 12], [$carla, 80_000, 5],
            [$bruno, 150_000, 20],
            [$ana, 200_000, 25], [$ana, 95_000, 3],
            [$seller1, 175_000, 8],
        ] as [$seller, $cents, $daysAgo]) {
            $distributor->distribute($submit($seller, $cents, $daysAgo), $admin);
        }

        // One pending sale for the admin queue
        $submit($carla, 60_000, 1);
    }
}
```

In `database/seeders/DatabaseSeeder.php`, at the end of `run()` add:
```php
$this->call(CommissionDemoSeeder::class);
```

- [ ] **Step 3: Run the full suite and build**

Run:
```bash
php artisan test
npm run build
```
Expected: ALL tests pass (existing 49 + ~40 new), assets build cleanly.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat(commissions): role-aware navigation and commission demo seeder"
```

---

## Final Verification

- [ ] `php artisan test` → all green.
- [ ] `npm run build` → no errors.
- [ ] **Live DB (MariaDB):** run `php artisan migrate --force` (NEVER `migrate:fresh` — the live DB has real accounts) then `php artisan db:seed --class=CommissionDemoSeeder --force`.
- [ ] Manual walkthrough on `php artisan serve`:
  1. Log in as `seller1@seller1.com` / `seller` — dashboard shows earnings cards + referral link; My Network shows Ana/Bruno/Carla/Diego; My Commissions lists upline payouts.
  2. Copy the referral link, log out, open it — register page shows "Sponsored by Seller One".
  3. Log in as `admin@admin.com` / `admin` — Sales shows the pending demo sale: approve it, watch payouts appear; Bonus Pool shows entries; Network shows the full forest.
  4. Open `/calculator`, run $1000 with 9 active uplines — totals reconcile to $199.80.
  5. Toggle ES and re-check the pages.
