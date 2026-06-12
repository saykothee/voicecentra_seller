# Locale Cookie + Sponsor Dropdown Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the language selection persist system-wide via a long-lived cookie (default English), and replace the change-sponsor email input with a dropdown of eligible sponsors.

**Architecture:** (1) `LocaleController` queues a forever `locale` cookie alongside the session value; `SetLocale` resolves session → cookie (re-hydrating the session) → `config('app.locale')`, validating each against `['en','es']`. (2) `AdminSellerController::editSponsor` computes eligible sponsors (approved sellers minus the seller's own subtree minus over-depth candidates) with the existing `SellerTree` service; the view renders them as a `<select name="sponsor_email">` so the backend contract and all existing tests stay untouched.

**Tech Stack:** Laravel 11, Breeze Blade, Pest on SQLite.

**Branch:** `build/locale-cookie-sponsor-dropdown` off `main`.

---

## Task 1: Persistent locale cookie

**Files:**
- Modify: `app/Http/Controllers/LocaleController.php`, `app/Http/Middleware/SetLocale.php`
- Test: `tests/Feature/LocaleTest.php`

- [ ] **Step 1: Write the failing tests**

In `tests/Feature/LocaleTest.php`, add `use App\Models\User;` and `uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);` at the top (the new logout test needs the DB; harmless for the existing tests), then append:
```php
test('switching the locale queues a persistent cookie', function () {
    $this->get('/lang/es')->assertCookie('locale', 'es');
});

test('the locale cookie alone sets the language on a fresh session', function () {
    $this->withCookie('locale', 'es')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'es'), false);
});

test('the language survives logout', function () {
    $user = User::factory()->approvedSeller()->create();

    $this->actingAs($user)->get('/lang/es')->assertCookie('locale', 'es');
    $this->actingAs($user)->post('/logout'); // invalidates the session

    $this->withCookie('locale', 'es')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'es'), false);
});

test('an invalid locale cookie falls back to english', function () {
    $this->withCookie('locale', 'fr')
        ->get('/')
        ->assertSee(__('messages.hero_title', [], 'en'), false);
});
```
(`withCookie()` is encrypted by the test framework to match `EncryptCookies`; `assertCookie` compares the decrypted value.)

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=LocaleTest`
Expected: the cookie tests FAIL (no cookie queued; cookie ignored).

- [ ] **Step 3: Queue the cookie in `LocaleController`**

In `app/Http/Controllers/LocaleController.php`, change the `__invoke` body to:
```php
public function __invoke(Request $request, string $locale)
{
    if (in_array($locale, ['en', 'es'], true)) {
        session(['locale' => $locale]);
        cookie()->queue(cookie()->forever('locale', $locale));
    }

    return redirect()->back(fallback: route('landing'));
}
```

- [ ] **Step 4: Resolve session → cookie → default in `SetLocale`**

Replace the `handle` body in `app/Http/Middleware/SetLocale.php` with:
```php
public function handle(Request $request, Closure $next): Response
{
    $supported = ['en', 'es'];

    $locale = session('locale');

    if (! in_array($locale, $supported, true)) {
        $locale = $request->cookie('locale');

        if (in_array($locale, $supported, true)) {
            session(['locale' => $locale]); // re-hydrate after session loss (e.g. logout)
        }
    }

    if (! in_array($locale, $supported, true)) {
        $locale = config('app.locale'); // 'en' — the default for new visitors
    }

    app()->setLocale($locale);

    return $next($request);
}
```
(`SetLocale` is appended to the `web` group, so it runs after `EncryptCookies` — `$request->cookie('locale')` is already decrypted; a tampered cookie decrypts to null/garbage and fails the allowlist.)

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=LocaleTest` then the FULL suite.
Expected: PASS (116 + 4 new).

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: persist language selection in a long-lived cookie (default English)"
```

---

## Task 2: Eligible-sponsor dropdown on the change-sponsor page

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminSellerController.php`, `resources/views/admin/sellers/sponsor.blade.php`, `lang/en/messages.php`, `lang/es/messages.php`
- Test: `tests/Feature/SponsorChangeTest.php`

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/SponsorChangeTest.php`:
```php
test('the sponsor form lists only eligible sponsors in the dropdown', function () {
    $admin = User::factory()->admin()->create();

    $seller = User::factory()->approvedSeller()->create(['name' => 'Edited Seller']);
    User::factory()->approvedSeller()->withSponsor($seller)->create(['name' => 'Descendant Seller']);
    User::factory()->approvedSeller()->create(['name' => 'Eligible Sponsor']);
    User::factory()->pending()->create(['name' => 'Pending Person']);

    // A depth-9 candidate: the edited seller has height 2 (itself + descendant), 9 + 2 > 10.
    $chain = [User::factory()->approvedSeller()->create(['name' => 'Deep Root'])];
    for ($i = 1; $i < 9; $i++) {
        $chain[] = User::factory()->approvedSeller()->withSponsor($chain[$i - 1])->create(['name' => 'Deep '.$i]);
    }

    $response = $this->actingAs($admin)
        ->get(route('admin.sellers.sponsor.edit', $seller))
        ->assertOk();

    $response->assertSee('Eligible Sponsor');
    $response->assertSee(__('messages.none_top_level'));
    $response->assertSee('Deep 7');           // depth 8: 8 + 2 = 10, still eligible
    $response->assertDontSee('Descendant Seller'); // own subtree (covers self-exclusion path)
    $response->assertDontSee('Pending Person');    // not approved
    $response->assertDontSee('Deep 8');            // depth 9: 9 + 2 > 10
});
```
(Self-exclusion can't be asserted by name — the edited seller's name appears in the page header — but the subtree exclusion uses the same excluded-ids set, which always contains the seller.)

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter="sponsor form lists"`
Expected: FAIL — `messages.none_top_level` renders as the literal key / dropdown absent.

- [ ] **Step 3: Add the i18n keys**

Append to the array in `lang/en/messages.php`:
```php
    'new_sponsor' => 'New sponsor',
    'none_top_level' => 'None (top level)',
```
Append to the array in `lang/es/messages.php`:
```php
    'new_sponsor' => 'Nuevo patrocinador',
    'none_top_level' => 'Ninguno (primer nivel)',
```

- [ ] **Step 4: Compute eligible sponsors in `editSponsor`**

In `app/Http/Controllers/Admin/AdminSellerController.php`, replace `editSponsor` with (SellerTree is already imported for `updateSponsor`):
```php
public function editSponsor(User $user, SellerTree $tree)
{
    abort_unless($user->isSeller(), 404);

    $excludedIds = $tree->subtreeUsers($user->id)->pluck('id');
    $maxParentDepth = (int) config('commissions.max_depth') - $tree->subtreeHeight($user);

    $eligibleSponsors = User::where('role', 'seller')
        ->where('status', 'approved')
        ->whereNotIn('id', $excludedIds)
        ->where('depth', '<=', $maxParentDepth)
        ->orderBy('name')
        ->get();

    return view('admin.sellers.sponsor', [
        'seller' => $user,
        'eligibleSponsors' => $eligibleSponsors,
    ]);
}
```

- [ ] **Step 5: Replace the input with a dropdown**

In `resources/views/admin/sellers/sponsor.blade.php`, replace the `<div>` containing the `sponsor_email` label/input/error with:
```blade
<div>
    <x-input-label for="sponsor_email" :value="__('messages.new_sponsor')" />
    <select id="sponsor_email" name="sponsor_email"
            class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
        <option value="">{{ __('messages.none_top_level') }}</option>
        @foreach ($eligibleSponsors as $candidate)
            <option value="{{ $candidate->email }}"
                    @selected(old('sponsor_email', $seller->parent?->email) === $candidate->email)>
                {{ $candidate->name }} ({{ $candidate->email }})
            </option>
        @endforeach
    </select>
    <x-input-error :messages="$errors->get('sponsor_email')" class="mt-2" />
</div>
```
(The field name and email values keep `updateSponsor` and its five tests untouched; server-side validation remains as defense in depth.)

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter=SponsorChangeTest` then the FULL suite + `npm run build`.
Expected: all PASS (6 SponsorChange tests; suite 121).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat(admin): eligible-sponsor dropdown on the change-sponsor page"
```

---

## Final Verification

- [ ] Full suite green; `npm run build` clean.
- [ ] Live check: switch to ES, click through landing/dashboard/network, log out and back in — language stays Spanish; `/admin/sellers` → Change sponsor shows the dropdown.
