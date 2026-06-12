# Seller Commission System — Design

**Date:** 2026-06-11
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-10-voicecentra-sellers-portal-design.md`

## Overview

Adds a multi-level commission system to the VoiceCentra Sellers Portal. Sellers form
a sponsorship tree (unlimited width, max chain depth 10). Sellers submit sales;
admins approve them, which distributes commissions to the seller and up to 9 upline
levels with an activity gate on levels 4–9. Unclaimed amounts accrue to a bonus pool.
Both dashboards visualize the network as an expandable tree; a calculator page lets
admins and sellers simulate the payout logic.

### Goals
- Seller tree with referral-link signup, depth-10 enforcement, and admin sponsor override.
- Sales workflow: seller submits → admin approves/rejects; refund reversal.
- Exact, auditable commission distribution (immutable ledgers, integer cents).
- Network visualization: seller sees own downline; admin sees the whole forest.
- Interactive commission calculator running the production math.

### Non-goals (this phase)
- Automatic bonus-pool distribution (manual/executive decision; we only track it).
- Payment processing/withdrawals — payouts are ledger entries, not money movement.
- Admin creating sales on behalf of sellers.
- Notifications (email or in-app) for sale approval/payouts.

## Commission Rules (authoritative)

Constants (in `config/commissions.php`; gate threshold env-overridable):
- `SELLER_RATE` = 10% of sale amount, always paid to the seller who closed the sale.
- `MAX_DEPTH` = 9 upline levels (chain of at most 10 people).
- `AUTO_LEVELS` = 3 — upline levels 1–3 always pay if the upline exists.
- `MIN_SALES_QUARTER` = 2 (env `COMMISSION_MIN_SALES_QUARTER`) — activity gate for levels 4–9.
- `ACTIVITY_WINDOW_DAYS` = 90.

Upline level k (k=1 is the direct sponsor): rate(k) = 0.10 / 2^k.

**Exact integer arithmetic:** all rates are binary fractions with common denominator
5120: seller = 512/5120, L1 = 256/5120, L2 = 128/5120 … L9 = 1/5120;
total = **1023/5120** (= 19.98046875%) of the sale, charged on every sale.

Per sale (amounts in integer cents):
- `payout(n) = floor(amount_cents × n / 5120)` for each recipient's numerator n.
- `total_charge = floor(amount_cents × 1023 / 5120)`.
- `pool_contribution = total_charge − Σ(paid payouts)` — absorbs skipped levels,
  missing levels, and rounding remainders. Always ≥ 0.
- **Invariant (asserted in code):** seller payout + upline payouts + pool contribution
  = total_charge, exactly.

### Activity gate
- Levels 1–3: paid whenever the upline exists.
- Levels 4–9: paid only if the upline is **active** = has ≥ `MIN_SALES_QUARTER`
  approved sales with `sold_at` within the 90 days ending at the evaluated sale's
  `sold_at`. Evaluated once at distribution time; the result is snapshotted on the
  payout row and never recomputed. Refunded sales do not count toward activity from
  the moment they are refunded.
- Inactive upline or nonexistent level → that level's amount goes to the bonus pool
  (reason recorded per level).

## Data Model

**`users` (extended):**
| Column | Type | Purpose |
|---|---|---|
| `parent_id` | FK → users, nullable, nullOnDelete | sponsor; null = top-level |
| `depth` | unsigned tinyint, default 1 | cached chain position (top-level = 1, max 10) |
| `referral_code` | string(8), unique | code for `/register?ref=CODE`; backfilled for existing sellers |

**`sales`:**
| Column | Type |
|---|---|
| `seller_id` | FK → users |
| `amount_cents` | unsigned bigint |
| `sold_at` | datetime (seller-reported) |
| `status` | enum: `pending`, `approved`, `rejected`, `refunded` |
| `notes` | string nullable |
| `approved_by` FK nullable, `approved_at` nullable | audit |

**`commission_payouts`** (immutable; written at approval):
| Column | Type |
|---|---|
| `sale_id`, `recipient_id` | FKs |
| `level` | unsigned tinyint: 0 = seller, 1–9 = upline |
| `rate_numerator` | unsigned smallint (n of n/5120; exact rate snapshot) |
| `amount_cents` | unsigned bigint |
| `recipient_was_active` | bool snapshot |
| `status` | enum: `paid`, `reversed` |

**`bonus_pool_entries`:**
| Column | Type |
|---|---|
| `sale_id` | FK |
| `level` | unsigned tinyint nullable (null = rounding remainder row) |
| `amount_cents` | signed bigint (negative for refund reversals) |
| `reason` | enum: `no_upline`, `inactive_upline`, `rounding`, `refund_reversal` |

Pool balance = `SUM(amount_cents)`.

## Engine Behavior

1. **Distribution moment:** runs once, in a DB transaction, when an admin approves a
   pending sale. Walk ≤9 uplines via `parent_id`, apply gate, write payout rows +
   pool entries, assert the invariant. A sale can only be approved from `pending`
   (status guard prevents double distribution).
2. **Snapshot:** payout rows are the chain snapshot (recipient, level, rate,
   active flag). Later sponsor reassignment never touches historical rows.
3. **Refund:** admin marks an `approved` sale `refunded` → all its payout rows flip
   to `reversed` and one negative `bonus_pool_entries` row (reason
   `refund_reversal`) offsets that sale's total pool contribution. Earnings queries
   count only `paid` rows.
4. **Reject:** `pending` → `rejected`; nothing distributed.

## Tree Rules

- New seller via `/register?ref=CODE`: sets `parent_id`, `depth = parent.depth + 1`.
  - Sponsor at depth 10 → validation error (chain full).
  - Invalid/unknown code → validation error (no silent top-level signups).
  - No code → top-level seller (`parent_id` null, `depth` 1).
- Admin sponsor override (on `/admin/sellers`): validates the new parent is not in
  the seller's own subtree (no cycles) and `new_parent.depth + height(seller's
  subtree) ≤ 10`; updates `parent_id` and re-caches `depth` for the whole moved
  subtree in a transaction. Affects future sales only.
- Architecture: adjacency list (`parent_id`) + cached `depth`; subtree reads use one
  recursive CTE (MariaDB 10.4+). No closure table — depth is capped at 10.

## Pages & Routes

| Route | Access | Description |
|---|---|---|
| `/seller/network` | approved seller | tree rooted at self (downline only) + "Your sponsor: Name" line; full upline chain hidden for privacy |
| `/seller/sales` | approved seller | own sales list + "Report sale" form (amount, date, notes) → `pending` |
| `/seller/commissions` | approved seller | own payout ledger (date, level, source sale's seller, amount, status) |
| `/admin/network` | admin | full forest (every top-level root), same tree component |
| `/admin/sales` | admin | all sales, status filter; Approve/Reject on pending; Refund on approved |
| `/admin/bonus-pool` | admin | pool balance + entries ledger |
| `/calculator` (GET+POST) | admins + approved sellers | commission simulator |

**Dashboard extensions:**
- Seller: earnings cards (total earned, last 30 days, pending sales count) + referral
  link with copy button + recent payouts.
- Admin: pending-sales count, total approved sales volume, commissions paid, pool balance.

**Navigation:** role-aware links — seller: Network, Sales, Commissions, Calculator;
admin: + Bonus Pool. All localized.

## Tree Component (visualization style A)

Recursive Blade partial + Alpine expand/collapse (Breeze ships Alpine). Each node:
initial-avatar, name, active/inactive badge (computed *as of now* with the same
gate parameters), level relative to root, approved-sales count (trailing 90 days),
downline count. Loaded with two queries (recursive CTE subtree + grouped sales
counts), assembled in PHP — no N+1.

## Calculator

Server-rendered form: sale amount, uplines present (0–9), per-level active toggles
for levels 4–9 (levels 1–3 always pay). POST runs the production
`CommissionCalculator` service and renders: per-level table (rate %, amount, paid
vs → pool with reason), seller cut, uplines total, pool total, company cost
(19.98046875%), invariant check line. EN/ES localized.

## Services (single source of truth)

- `App\Services\CommissionCalculator` — pure function: `(amount_cents,
  uplineSlots[level => activeBool|null]) → {payouts[], poolEntries[], totalCharge}`.
  No DB access. Used by distributor, calculator page, and tests.
- `App\Services\CommissionDistributor` — loads the real chain + activity data,
  delegates math to the calculator, persists ledgers in a transaction, asserts the
  invariant.

## Seeding

Extend `DatabaseSeeder`: small demo tree under seller1 (3 levels of demo sellers,
password `seller`), several approved sales distributed through the real
`CommissionDistributor`, so network pages, earnings cards, and the pool ledger have
data on first run.

## Testing (Pest)

- **Calculator unit:** exact splits for full chain all-active, short chain, inactive
  L4+; penny amounts ($0.01, $99.99, odd cents); invariant always reconciles;
  remainder → pool.
- **Distribution feature:** approval writes correct payout + pool rows; activity
  gate evaluated against the 90-day window anchored at `sold_at` and snapshotted;
  refund reverses payouts and offsets pool; approving a non-pending sale fails.
- **Tree:** referral signup sets parent/depth; chain-full (depth 10) rejected;
  invalid code rejected; sponsor reassignment rejects cycles and over-depth moves,
  re-caches subtree depths.
- **Authorization:** seller cannot view another seller's subtree or payouts;
  non-admin cannot approve/reject/refund or view the pool; pending sellers cannot
  submit sales; calculator blocked for pending sellers, allowed for admins +
  approved sellers.

## Compliance (seller agreement, not code)
- Commissions are paid only on real product sales; nothing is paid for recruiting.
- The level 4–9 activity requirement is based solely on the upline's own sales.
- The year-end bonus pool is discretionary, at the company's sole determination.
