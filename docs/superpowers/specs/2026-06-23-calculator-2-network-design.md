# Calculator 2.0 (Network Commission Calculator) — Design

**Date:** 2026-06-23
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-11-commission-system-design.md`, the existing `/calculator`,
and `2026-06-22-configuration-min-sales-design.md` (min-sales-per-age brackets).

## Overview

A second commission calculator that runs over the **real** sponsor network instead
of an abstract count of uplines. The operator picks a real seller and enters a
sales amount; the page shows that seller plus their real upline chain (L1..L9) **by
name**, multiplies the sales amount by each member's **minimum sales requirement for
their age**, and then distributes the seller's resulting (grossed-up) amount as
commission across the real chain.

The original `/calculator` (abstract, manual upline toggles) stays untouched as a
fallback. Calculator 2.0 is a sibling page that reuses the existing commission math.

## Architecture

- **New** `App\Http\Controllers\NetworkCalculatorController` — `show` + `compute`.
- **New** `App\Services\MinSalesLookup` — the only genuinely new logic: map a User to
  their age, min-sales bracket, and multiplier. Pure, no side effects, unit-tested.
- **Reused unchanged:**
  - `App\Services\CommissionCalculator::calculate(int $amountCents, array $uplineSlots)`
    — the exact commission split (seller cut, per-level cuts, bonus pool, rounding,
    company total). Preserves the invariant `seller + paid uplines + pool == total`.
  - `App\Services\CommissionDistributor::isActive(User $upline, Carbon $at)` — real
    activity check (≥ `min_sales_quarter` approved sales within
    `activity_window_days`). Called with `now()` as the reference date.
  - `User::uplineChain()` — level (1..9) => upline User.
  - `App\Models\MinSalesRequirement::forAge($age)` scope and `label()`.
- **New** view `resources/views/calculator-2.blade.php`.

## Access Control

Same rule as the base calculator: `abort_unless($user->isAdmin() || ($user->isSeller()
&& $user->isApproved()), 403)` in both `show` and `compute`.

## Routes

In `routes/web.php`, alongside the existing `calculator` routes (same auth group):

```php
Route::get('/calculator-2', [\App\Http\Controllers\NetworkCalculatorController::class, 'show'])->name('calculator2');
Route::post('/calculator-2', [\App\Http\Controllers\NetworkCalculatorController::class, 'compute'])->name('calculator2.compute');
```

## Inputs

`POST /calculator-2`:
- `seller_id` — required, integer, must exist in `users` with `role = seller`
  (`Rule::exists('users','id')->where('role','seller')`). Populated from a dropdown
  of **all** sellers (`role=seller`), ordered by name — you can model any seller's
  chain, not only approved ones.
- `amount` — required, numeric, `min:0.01`, `max:100000000` (matches the base
  calculator).

## `MinSalesLookup` Service

```php
namespace App\Services;

use App\Models\MinSalesRequirement;
use App\Models\User;

class MinSalesLookup
{
    /**
     * @return array{age: ?int, label: ?string, min_sales: int, matched: bool}
     *   matched=false means no DOB or no bracket covers the age; multiplier falls
     *   back to 1 (min_sales=1) so the row still shows the raw amount.
     */
    public function forUser(User $user): array
    {
        $age = $user->age; // null when date_of_birth is null

        $bracket = $age === null
            ? null
            : MinSalesRequirement::forAge($age)->first();

        return $bracket
            ? ['age' => $age, 'label' => $bracket->label(), 'min_sales' => $bracket->min_sales, 'matched' => true]
            : ['age' => $age, 'label' => null, 'min_sales' => 1, 'matched' => false];
    }
}
```

## Computation Flow

1. Load the selected seller. Build the member list: index 0 = seller, then
   `seller->uplineChain()` (1..9 => real upline Users).
2. For **each** member, call `MinSalesLookup::forUser`. Compute
   `effective_cents = amountCents * lookup.min_sales` where
   `amountCents = (int) round($amount * 100)`. (Integer × integer — exact.)
3. **Per-person table** (one row per member): level label (`Seller`, `L1`..`L9`),
   name, age (or "—"), bracket label (or "no requirement" when `matched=false`),
   `min_sales` multiplier, and `effective` amount. Plus a grand-total row summing
   every member's effective amount.
4. **Commission split:** take the **seller's** `effective_cents`
   (`amountCents × seller's min_sales`) as the sale amount. Build
   `uplineSlots[level] = isActive($chain[$level], now())` for each real upline.
   Call `CommissionCalculator::calculate($sellerEffectiveCents, $uplineSlots)`.
5. **Results table** mirrors the base calculator but labels each level row with the
   real upline's **name** (or "no upline" where the chain ends / the level is
   missing): seller cut, L1..L9 cuts with destination (paid / pool-inactive /
   pool-no-upline), rounding remainder, uplines total, pool total, company cost.
   The header states the effective amount being distributed and how it was derived
   (`amount × seller's min_sales`).

## Data Flow Summary

```
amount + seller_id
   │
   ├─ per member m in [seller, upline L1..L9]:
   │     lookup = MinSalesLookup(m)
   │     effective_m = amountCents × lookup.min_sales      → per-person table
   │
   └─ sellerEffective = amountCents × sellerLookup.min_sales
         uplineSlots[L] = isActive(uplineL, now())
         CommissionCalculator.calculate(sellerEffective, uplineSlots)  → split table
```

## Error Handling & Edge Cases

- **Validation failure** (missing/non-seller `seller_id`, bad `amount`) → redirect
  back with Laravel's error bag; the form repopulates from `old()`.
- **Seller with no uplines** (top-level, `parent_id` null) → empty chain; every
  upline level in the split falls to the pool with reason `no_upline`. The per-person
  table shows just the seller row.
- **No DOB / age outside all brackets** → `matched=false`, multiplier ×1, row flagged
  "no requirement". The seller's split then uses the raw amount.
- **Chain shorter than 9** → only the existing levels appear as uplines; missing
  levels are pool `no_upline`, exactly as `CommissionCalculator` already handles.

## Navigation

Add a "Calculator 2.0" link beside the existing "Calculator" link in
`resources/views/layouts/navigation.blade.php`: in the admin desktop group, the
seller desktop group, and both responsive groups. New `messages.calculator_2` i18n
key (en + the existing second locale).

## Testing (Pest)

**Unit — `tests/Unit/MinSalesLookupTest.php`:**
- age 20 → min_sales 10, label "18–29", matched true.
- age 35 → 8; age 48 → 6; age 60 → 4; age 85 → 2 (label "80+").
- user with null `date_of_birth` → min_sales 1, matched false, age null.
- age below all brackets (e.g. 15) → min_sales 1, matched false.

**Feature — `tests/Feature/NetworkCalculatorTest.php`:**
- Guest → redirected to login.
- Authenticated non-approved seller → 403; admin → 200; approved seller → 200.
- `show` renders the seller dropdown.
- Compute on a built chain Carla(35) → Bruno(30) → Ana(48) → SellerOne(20) with
  `amount = 100`:
  - per-person effective amounts: Carla 100×8=800, Bruno 100×8=800… (assert by the
    member's own min_sales — Carla 8, Bruno 8, Ana 6, SellerOne 10).
  - seller (Carla) effective = 100×8 = 800 → `seller_cents` = 10% of 80000 cents.
  - upline level rows are labeled with Bruno / Ana / Seller One names.
  - commission invariant holds (seller + paid uplines + pool == total).
- Seller with null DOB → per-person row flagged not-matched, multiplier ×1, split
  uses raw amount.
- Validation: missing `seller_id`, a non-seller (admin) `seller_id`, and
  `amount = 0` each produce a validation error.

## Out of Scope

- No persistence — this is a what-if calculator, identical in spirit to `/calculator`.
  Nothing is written to `sales` or `commission_payouts`.
- No downline/whole-network views (this phase is seller + upline chain only).
- No change to the existing `/calculator`.
