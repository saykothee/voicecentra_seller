# Persistent Locale Cookie — Design

**Date:** 2026-06-12
**Status:** Approved

## Problem

The locale lives only in the session. Logout (`session()->invalidate()`), session
expiry, and host changes wipe it, so the language appears to "keep changing".
Default must remain English.

## Behavior

- `LocaleController` (`/lang/{locale}`): in addition to setting `session('locale')`,
  queue a forever cookie `locale=<en|es>` (5-year lifetime, httpOnly, encrypted by
  Laravel's standard cookie encryption).
- `SetLocale` middleware resolves the locale by precedence, validating each value
  against `['en', 'es']`:
  1. `session('locale')`
  2. `$request->cookie('locale')` — when used, also write it back into the session
     (re-hydration after login/logout)
  3. `config('app.locale')` (= `en`, the default for new visitors)
- Invalid or tampered cookie values are ignored → English.

## Testing (Pest)

1. Hitting `/lang/es` queues the `locale` cookie.
2. A request carrying only the cookie (fresh session) renders Spanish.
3. The locale survives logout (cookie persists across session invalidation).
4. An invalid cookie value falls back to English.
