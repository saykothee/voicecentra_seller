# Configuration → Min Sales (age-based requirements) — Design

**Date:** 2026-06-22
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-22-admin-profile-edit-dob-design.md`

## Overview

Add an admin **Configuration** menu with a **Min sales** page where an admin sets
the minimum number of sales a seller must make, by age bracket. A new
`min_sales_requirements` table stores one row per age bracket (`min_age`,
`max_age`, `min_sales`). The age ranges are fixed; only `min_sales` is editable.
This phase builds the table, the menu, and the editor. The eventual goal — comparing
each seller's age against their bracket to flag compliance — is a later phase; to
prepare for it, the model gets a `forAge()` scope (unit-tested now).

### In scope
- `min_sales_requirements` table + `MinSalesRequirement` model with a `forAge` scope.
- Seeder populating the 7 fixed brackets with editable default `min_sales`.
- Admin-only "Configuration" nav dropdown with a "Min sales" item.
- `/admin/configuration/min-sales` page to view and edit all `min_sales` values.
- EN/ES localization.

### Out of scope (later phase)
- The compliance comparison view/column (which sellers meet their requirement).
- Editing the age ranges themselves, or adding/removing brackets.
- Any tie-in to the existing commission activity gate (`min_sales_quarter`) — that
  stays independent; this is a separate, generic requirement value.

## Semantics

`min_sales` is a plain configurable integer for now — no fixed measurement period.
The UI labels it generically ("Minimum sales"); the period will be defined when the
comparison feature is built.

## Data Model

**Table `min_sales_requirements`:**
| Column | Type | Notes |
|---|---|---|
| `id` | bigint pk | |
| `min_age` | unsigned tinyint | bracket lower bound, inclusive |
| `max_age` | unsigned tinyint, nullable | upper bound, inclusive; `null` = no upper limit (80+) |
| `min_sales` | unsigned int | the editable requirement |
| `timestamps` | | |

(These are the requested `min` / `max` / `min_sales` fields, named `min_age` /
`max_age` / `min_sales` for clarity and to avoid SQL-keyword friction.)

**Model `App\Models\MinSalesRequirement`:**
- `$fillable = ['min_age', 'max_age', 'min_sales']`.
- Casts `min_age`, `max_age`, `min_sales` to integer.
- Scope `forAge(int $age)`: `where('min_age', '<=', $age)->where(fn ($q) =>
  $q->whereNull('max_age')->orWhere('max_age', '>=', $age))`. Returns the matching
  bracket (one row, by construction of non-overlapping brackets).
- Accessor/helper `label()`: returns `"{min_age}–{max_age}"`, or `"{min_age}+"`
  when `max_age` is null (e.g. `"18–29"`, `"80+"`).

## Brackets & Seeding

Seeded once (idempotent — skip if rows already exist). Age ranges fixed; defaults
editable afterward:

| Bracket | min_age | max_age | default min_sales |
|---|---|---|---|
| 18–29 | 18 | 29 | 10 |
| 30–39 | 30 | 39 | 8 |
| 40–49 | 40 | 49 | 6 |
| 50–59 | 50 | 59 | 5 |
| 60–69 | 60 | 69 | 4 |
| 70–79 | 70 | 79 | 3 |
| 80+ | 80 | null | 2 |

The first bracket starts at **18** (not 19) so every valid seller — registration
requires 18+ — is covered. Seeding lives in a dedicated
`MinSalesRequirementSeeder` called from `DatabaseSeeder`.

## Navigation

A new admin-only **Configuration** dropdown in the top nav (reusing Breeze's
`x-dropdown` component, like the existing user-settings menu), containing one link:
**Min sales** → `admin.configuration.min-sales`. The dropdown trigger shows an
active state when on any `admin.configuration.*` route. The responsive (mobile)
menu gets a "Configuration" sub-heading with the same link. Sellers never see it.

## Page & Controller

`App\Http\Controllers\Admin\AdminMinSalesController`:
- `index()` — loads all requirements ordered by `min_age`, returns
  `view('admin.configuration.min-sales', compact('requirements'))`.
- `update(Request $request)` — validates and saves every row's `min_sales`.

**View `resources/views/admin/configuration/min-sales.blade.php`:** a single form
(PATCH) with a table — each row shows the read-only bracket label and a number
input named `min_sales[{id}]`, pre-filled. One **Save** button. Flash on success.

**Validation:**
```
'min_sales'        => ['required', 'array'],
'min_sales.*'      => ['required', 'integer', 'min:0'],
```
On update: iterate the posted `min_sales` map, and for each existing requirement id
set `min_sales` and save (ignore unknown ids). Redirect back with
`messages.min_sales_updated`.

**Routes** (inside the existing `['auth','admin']` admin group):
```
GET   /admin/configuration/min-sales  -> admin.configuration.min-sales
PATCH /admin/configuration/min-sales  -> admin.configuration.min-sales.update
```

## i18n

New keys in BOTH catalogs (parity-tested): `configuration`, `min_sales_title`
("Minimum sales by age"), `age_range`, `minimum_sales`, `min_sales_intro`
(short explanation), `min_sales_updated` ("Minimum sales updated."). Reuse existing
`save`. EN/ES values provided in the plan.

## Testing (Pest)

- The seeder creates exactly 7 brackets with the expected ranges; `80+` has
  `max_age = null`.
- `forAge` returns the correct bracket for representative ages and boundaries:
  18→18–29, 29→18–29, 30→30–39, 79→70–79, 80→80+, 95→80+.
- `label()` renders `"18–29"` and `"80+"`.
- Admin can open the page (200) and sees all bracket labels.
- Admin update saves new `min_sales` values for every row.
- Update rejects a negative / non-integer `min_sales` (session has errors).
- A non-admin (approved seller) is forbidden from the page and the update route.
- en/es `messages` key parity holds.
