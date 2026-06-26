# External Sales Ingestion Endpoint — Design

**Date:** 2026-06-22
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-11-commission-system-design.md`

## Overview

A machine-to-machine JSON API endpoint that lets a different system push sale
records for sellers. The caller authenticates with a JWT (HS256, shared secret) in
the `Authorization` header; the sale fields arrive in the JSON body. Records are
stored directly in the existing `sales` table as **already-approved** sales (the
caller is a trusted system). The dedicated `external_sales` table was removed
(migration `2026_06_22_160000_merge_external_sales_into_sales_table`).

**Field mapping into `sales`:** `sale_date` → `sold_at`, `amount` → `amount_cents`,
`paid_at` → `paid_at`, `paid` → `paid`, `free_trial` → `trial`. `seller_id`,
`status` (`approved`), and `approved_at` (`now()`) are set explicitly (not
mass-assignable); `approved_by` stays null (no admin approved it). The endpoint
records the sale only — it does NOT trigger commission distribution.

## Authentication

- Library: `firebase/php-jwt`.
- Shared secret in `.env` as `EXTERNAL_SALES_JWT_SECRET`, exposed via
  `config/external_sales.php` (`config('external_sales.jwt_secret')`).
- Middleware `App\Http\Middleware\VerifyExternalJwt` (alias `external.jwt`):
  - Reads the bearer token from the `Authorization: Bearer <token>` header.
  - Decodes/verifies it with HS256 against the shared secret
    (`JWT::decode($token, new Key($secret, 'HS256'))`). firebase/php-jwt
    automatically rejects bad signatures and enforces `exp`/`nbf`/`iat` when present.
    NOTE: HS256 in firebase/php-jwt v7 requires the secret to be ≥ 32 bytes.
  - **Requires an `exp` claim**: after a successful decode, if the token has no
    `exp`, it is rejected (so a leaked token can't be replayed forever).
  - On any failure (missing header, malformed token, bad signature, expired,
    missing `exp`) → responds `401` JSON `{"message": "Unauthenticated."}`.
    No session, no redirect.
- The JWT only authenticates the caller. The sale payload is the JSON body, not the
  token claims.

## Routing

- No `routes/api.php` currently exists and Sanctum is NOT installed; we keep it that
  way (this is service-to-service, not user auth).
- Create `routes/api.php` and register it in `bootstrap/app.php` via
  `->withRouting(... api: __DIR__.'/../routes/api.php', ...)`. Routes get the `api`
  prefix automatically (so the path is `/api/external-sales`).
- Single route: `POST /api/external-sales` → `ExternalSaleController@store`, behind
  the `external.jwt` middleware.

## Data Model

**Table `sales`** (existing) — columns added for external ingestion:
| Column | Type | Notes |
|---|---|---|
| `paid_at` | datetime, nullable | when it was paid; null if not paid yet |
| `paid` | boolean, default false | |
| `trial` | boolean, default false | from the incoming `free_trial` flag |

Existing `sales` columns (`seller_id`, `amount_cents`, `sold_at`, `status`,
`approved_by`, `approved_at`, `notes`, `timestamps`) are reused. The external
sale's date lands in `sold_at`.

**Model `App\Models\Sale`** (existing):
- `$fillable = ['amount_cents', 'sold_at', 'paid_at', 'paid', 'trial', 'notes']`
  (`seller_id`/`status`/`approved_*` set explicitly, never mass-assigned).
- Casts add `paid_at` → datetime, `paid` → boolean, `trial` → boolean.

## Request / Response

`POST /api/external-sales` — body (JSON):
```json
{
  "seller_id": 2,
  "sale_date": "2026-06-20",
  "paid_at": "2026-06-21T14:30:00Z",
  "amount": 49.99,
  "paid": true,
  "free_trial": false
}
```

**Validation** (`422` with Laravel's JSON error bag on failure):
- `seller_id` — required, integer, must exist in `users` AND have `role = seller`
  (`Rule::exists('users','id')->where('role','seller')`) — an admin id is rejected
- `sale_date` — required, date
- `paid_at` — nullable, date
- `amount` — required, numeric, `min:0`, `max:1000000`
- `paid` — required, boolean
- `free_trial` — required, boolean

**Rate limiting:** the route group applies `throttle:120,1` (120 req/min per IP) —
tune to the sender's volume.

**Controller behavior:** convert `amount` to cents
(`(int) round($amount * 100)`), create the `ExternalSale` (set `seller_id` and the
rest), return **`201`** JSON `{"id": <id>, "status": "recorded"}`.

## Error Handling

- `401` — missing/invalid/expired JWT (from middleware).
- `422` — validation failure (Laravel default JSON shape, since the request expects
  JSON / hits the `api` group).
- `201` — success.
- Force JSON: requests to `/api/*` should always receive JSON errors. The `api`
  routes return JSON for validation automatically; the middleware returns JSON
  explicitly.

## Config & Secrets

- `config/external_sales.php`: `return ['jwt_secret' => env('EXTERNAL_SALES_JWT_SECRET')];`
- `.env` and `.env.example`: add `EXTERNAL_SALES_JWT_SECRET=` (placeholder in
  example; a real shared secret locally/production).

## Testing (Pest, feature)

Use a helper that mints a valid HS256 token with the test secret
(`config(['external_sales.jwt_secret' => 'test-secret'])` + `JWT::encode([...], 'test-secret', 'HS256')`).

- Valid token + valid payload → `201`; one `external_sales` row with
  `amount_cents = 4999`, correct `seller_id`, `paid`/`free_trial` cast to bool.
- `amount` decimal converts to cents (e.g. `49.99` → `4999`, `100` → `10000`).
- `paid = false` with `paid_at = null` → `201` (paid_at nullable).
- No `Authorization` header → `401`.
- Malformed token / wrong-secret signature → `401`.
- Expired token (`exp` in the past) → `401`.
- Missing required field (e.g. no `amount`) → `422`.
- Unknown `seller_id` (no such user) → `422`.

## Out of Scope / Notes

- No commission distribution — `external_sales` is independent of the `sales` table
  and its approval/payout flow. (A later phase could reconcile the two.)
- No idempotency/dedup: the payload carries no external reference id, so each valid
  POST inserts a row. If the sender can supply a unique id later, add a unique column
  + upsert.
- No admin UI for these records in this phase (storage + endpoint only).
