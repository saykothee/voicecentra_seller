# Admin Seller Profile Editing + Date of Birth Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a `date_of_birth` field (captured at registration, 18+), an `age` accessor, and an admin "Edit profile" action on `/admin/sellers` that edits name, email, phone, DOB, status, and role from one form with a live read-only Age field.

**Architecture:** New nullable `date_of_birth` column on `users` (cast to date) plus a computed `age` accessor. Registration gains a required 18+ DOB input. A new `AdminSellerController::edit/update` pair renders and persists a full profile form; `role`/`status` are set by explicit assignment (they stay non-fillable), status→approved mirrors the approve/reject audit stamping, and a self-edit guard ignores role/status changes to the acting admin's own account.

**Tech Stack:** Laravel 11, Breeze (Blade + Tailwind + Alpine), Pest on in-memory SQLite, MariaDB live.

**Branch:** create `build/admin-profile-edit` off `main` before Task 1.

---

## File Structure

**Created:**
- `database/migrations/xxxx_add_date_of_birth_to_users_table.php`
- `resources/views/admin/sellers/edit.blade.php` — full profile edit form
- `tests/Feature/AdminProfileEditTest.php`

**Modified:**
- `app/Models/User.php` — `date_of_birth` in `$fillable` + cast, `age` accessor
- `app/Http/Controllers/Auth/RegisteredUserController.php` — DOB validation (18+) + persistence
- `resources/views/auth/register.blade.php` — DOB input
- `app/Http/Controllers/Admin/AdminSellerController.php` — `edit` + `update`
- `resources/views/admin/sellers/index.blade.php` — "Edit" action link
- `routes/web.php` — edit/update routes
- `lang/en/messages.php`, `lang/es/messages.php` — new keys
- `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/ReferralRegistrationTest.php` — include `date_of_birth` in payloads

---

## Task 0: Branch

- [ ] From a clean `main`: `git checkout -b build/admin-profile-edit`

---

## Task 1: date_of_birth column, cast, fillable, age accessor

**Files:**
- Create: `database/migrations/xxxx_add_date_of_birth_to_users_table.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/AdminProfileEditTest.php`

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/AdminProfileEditTest.php`:
```php
<?php

use App\Models\User;
use Illuminate\Support\Carbon;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('the age accessor computes years from date_of_birth', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => '1990-06-15']);

    expect($user->date_of_birth)->toBeInstanceOf(Carbon::class);
    expect($user->age)->toBe(Carbon::parse('1990-06-15')->age);
});

test('the age accessor is null when no date_of_birth is set', function () {
    $user = User::factory()->approvedSeller()->create(['date_of_birth' => null]);

    expect($user->age)->toBeNull();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminProfileEditTest`
Expected: FAIL — unknown column `date_of_birth`.

- [ ] **Step 3: Create the migration**

Run: `php artisan make:migration add_date_of_birth_to_users_table`, then set contents to:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->date('date_of_birth')->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('date_of_birth');
        });
    }
};
```

- [ ] **Step 4: Update the User model**

In `app/Models/User.php`:
- Add `'date_of_birth',` to the `$fillable` array (after `'phone',`).
- Add `'date_of_birth' => 'date',` to the `casts()` array.
- Add the import `use Illuminate\Database\Eloquent\Casts\Attribute;` near the other `use` statements.
- Add this accessor method (e.g. right after `isRejected()`):
```php
protected function age(): Attribute
{
    return Attribute::make(
        get: fn () => $this->date_of_birth?->age,
    );
}
```

- [ ] **Step 5: Run tests**

Run: `php artisan test --filter=AdminProfileEditTest` then `php artisan test`
Expected: the two new tests PASS; full suite stays green.

- [ ] **Step 6: Commit**

```bash
git add -A
git commit -m "feat: add date_of_birth column, cast and age accessor to User"
```

---

## Task 2: Date of birth at registration (required, 18+)

**Files:**
- Modify: `app/Http/Controllers/Auth/RegisteredUserController.php`, `resources/views/auth/register.blade.php`, `tests/Feature/Auth/RegistrationTest.php`, `tests/Feature/ReferralRegistrationTest.php`
- Test: `tests/Feature/AdminProfileEditTest.php` (append registration cases)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminProfileEditTest.php`:
```php
test('registration requires date_of_birth and stores it', function () {
    $this->post('/register', [
        'name' => 'Dob Seller',
        'email' => 'dob@example.com',
        'phone' => '555-0100',
        'date_of_birth' => '1995-03-10',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'dob@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->date_of_birth->format('Y-m-d'))->toBe('1995-03-10');
});

test('registration rejects a missing date_of_birth', function () {
    $this->post('/register', [
        'name' => 'No Dob',
        'email' => 'nodob@example.com',
        'phone' => '555-0100',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'nodob@example.com')->exists())->toBeFalse();
});

test('registration rejects an under-18 date_of_birth', function () {
    $this->post('/register', [
        'name' => 'Too Young',
        'email' => 'young@example.com',
        'phone' => '555-0100',
        'date_of_birth' => now()->subYears(17)->toDateString(),
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('date_of_birth');

    expect(User::where('email', 'young@example.com')->exists())->toBeFalse();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminProfileEditTest`
Expected: the three new tests FAIL (DOB not validated/stored).

- [ ] **Step 3: Add validation + persistence in the controller**

In `app/Http/Controllers/Auth/RegisteredUserController.php`, add the DOB rule to the `$request->validate([...])` array (after the `phone` rule):
```php
'date_of_birth' => ['required', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears(18)->toDateString()],
```
Then add `'date_of_birth' => $request->date_of_birth,` to the `User::create([...])` array (after `'phone' => $request->phone,`).

- [ ] **Step 4: Add the DOB input to the register form**

In `resources/views/auth/register.blade.php`, insert after the Phone block (before the Password block):
```blade
<!-- Date of birth -->
<div class="mt-4">
    <x-input-label for="date_of_birth" :value="__('messages.date_of_birth')" />
    <x-text-input id="date_of_birth" class="block mt-1 w-full" type="date" name="date_of_birth"
                  :value="old('date_of_birth')" required
                  max="{{ now()->subYears(18)->toDateString() }}" />
    <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
</div>
```

- [ ] **Step 5: Fix the existing registration tests**

The new required field breaks existing register posts. Update both:
- `tests/Feature/Auth/RegistrationTest.php`: in the "new users can register" test, add `'date_of_birth' => '1990-01-01',` to the `$this->post('/register', [...])` payload.
- `tests/Feature/ReferralRegistrationTest.php`: in the `registrationPayload()` helper's base array, add `'date_of_birth' => '1990-01-01',`.

- [ ] **Step 6: Run tests**

Run: `php artisan test --filter="AdminProfileEditTest|Registration"` then full suite.
Expected: PASS (new DOB tests, Breeze registration test, and all referral-registration tests green).

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "feat: capture required 18+ date_of_birth at registration"
```

---

## Task 3: i18n keys

**Files:**
- Modify: `lang/en/messages.php`, `lang/es/messages.php`
- Test: `tests/Feature/LocaleParityTest.php` (existing parity test guards this)

- [ ] **Step 1: Add the English keys**

Append to the array in `lang/en/messages.php`:
```php
    // Profile edit / DOB
    'edit' => 'Edit',
    'edit_profile' => 'Edit profile',
    'date_of_birth' => 'Date of birth',
    'age' => 'Age',
    'role' => 'Role',
    'role_seller' => 'Seller',
    'role_admin' => 'Admin',
    'profile_updated' => 'Profile updated.',
```

- [ ] **Step 2: Add the Spanish keys**

Append to the array in `lang/es/messages.php`:
```php
    // Edición de perfil / fecha de nacimiento
    'edit' => 'Editar',
    'edit_profile' => 'Editar perfil',
    'date_of_birth' => 'Fecha de nacimiento',
    'age' => 'Edad',
    'role' => 'Rol',
    'role_seller' => 'Vendedor',
    'role_admin' => 'Administrador',
    'profile_updated' => 'Perfil actualizado.',
```

- [ ] **Step 3: Run the parity test**

Run: `php artisan test --filter=LocaleParityTest` then full suite.
Expected: PASS (en/es key sets identical).

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "feat: EN/ES keys for profile edit and date of birth"
```

---

## Task 4: Admin edit-profile action

**Files:**
- Modify: `app/Http/Controllers/Admin/AdminSellerController.php`, `resources/views/admin/sellers/index.blade.php`, `routes/web.php`
- Create: `resources/views/admin/sellers/edit.blade.php`
- Test: `tests/Feature/AdminProfileEditTest.php` (append)

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminProfileEditTest.php`:
```php
test('admin can open the edit page for a seller', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['name' => 'Edit Me']);

    $this->actingAs($admin)->get(route('admin.sellers.edit', $seller))
        ->assertOk()
        ->assertSee('Edit Me')
        ->assertSee(__('messages.date_of_birth'));
});

test('admin update saves name, email, phone and date_of_birth', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => 'New Name',
        'email' => 'new.email@example.com',
        'phone' => '555-9999',
        'date_of_birth' => '1988-02-20',
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertRedirect(route('admin.sellers.index'));

    $seller->refresh();
    expect($seller->name)->toBe('New Name');
    expect($seller->email)->toBe('new.email@example.com');
    expect($seller->phone)->toBe('555-9999');
    expect($seller->date_of_birth->format('Y-m-d'))->toBe('1988-02-20');
});

test('admin can change role and approving via edit stamps the audit fields', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->pending()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => 'approved',
        'role' => 'admin',
    ])->assertRedirect();

    $seller->refresh();
    expect($seller->role)->toBe('admin');
    expect($seller->status)->toBe('approved');
    expect($seller->approved_by)->toBe($admin->id);
    expect($seller->approved_at)->not->toBeNull();
});

test('moving a seller away from approved clears the audit fields', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['approved_by' => $admin->id, 'approved_at' => now()]);

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => 'rejected',
        'role' => 'seller',
    ])->assertRedirect();

    $seller->refresh();
    expect($seller->status)->toBe('rejected');
    expect($seller->approved_at)->toBeNull();
    expect($seller->approved_by)->toBeNull();
});

test('email uniqueness ignores the edited user', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create(['email' => 'keep@example.com']);

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => 'keep@example.com',
        'phone' => $seller->phone,
        'date_of_birth' => null,
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertRedirect();

    expect($seller->fresh()->email)->toBe('keep@example.com');
});

test('admin update rejects an under-18 date_of_birth', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->approvedSeller()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $seller), [
        'name' => $seller->name,
        'email' => $seller->email,
        'phone' => $seller->phone,
        'date_of_birth' => now()->subYears(10)->toDateString(),
        'status' => $seller->status,
        'role' => 'seller',
    ])->assertSessionHasErrors('date_of_birth');
});

test('an admin editing their own account cannot change their own role or status', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->patch(route('admin.sellers.update', $admin), [
        'name' => 'Renamed Admin',
        'email' => $admin->email,
        'phone' => $admin->phone,
        'date_of_birth' => null,
        'status' => 'rejected',
        'role' => 'seller',
    ])->assertRedirect();

    $admin->refresh();
    expect($admin->name)->toBe('Renamed Admin'); // profile fields still save
    expect($admin->role)->toBe('admin');         // role change ignored
    expect($admin->status)->toBe('approved');     // status change ignored
});

test('non-admins are forbidden from the edit and update routes', function () {
    $seller = User::factory()->approvedSeller()->create();
    $other = User::factory()->approvedSeller()->create();

    $this->actingAs($seller)->get(route('admin.sellers.edit', $other))->assertForbidden();
    $this->actingAs($seller)->patch(route('admin.sellers.update', $other), [
        'name' => 'x', 'email' => 'x@example.com', 'phone' => '1', 'status' => 'approved', 'role' => 'seller',
    ])->assertForbidden();
});
```

- [ ] **Step 2: Run to verify failure**

Run: `php artisan test --filter=AdminProfileEditTest`
Expected: the new tests FAIL — routes not defined.

- [ ] **Step 3: Add `edit` and `update` to `AdminSellerController`**

In `app/Http/Controllers/Admin/AdminSellerController.php`, add `use Illuminate\Validation\Rule;` to the imports, then add these two methods:
```php
public function edit(User $user)
{
    return view('admin.sellers.edit', ['seller' => $user]);
}

public function update(Request $request, User $user)
{
    $data = $request->validate([
        'name' => ['required', 'string', 'max:255'],
        'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        'phone' => ['required', 'string', 'max:30'],
        'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:'.now()->subYears(18)->toDateString()],
        'status' => ['required', 'in:pending,approved,rejected'],
        'role' => ['required', 'in:seller,admin'],
    ]);

    $user->name = $data['name'];
    $user->email = $data['email'];
    $user->phone = $data['phone'];
    $user->date_of_birth = $data['date_of_birth'] ?? null;

    // Self-lockout guard: never change your own role/status from this admin form.
    if ($user->id !== auth()->id()) {
        $user->role = $data['role'];

        if ($data['status'] === 'approved' && $user->status !== 'approved') {
            $user->status = 'approved';
            $user->approved_at = now();
            $user->approved_by = auth()->id();
        } elseif ($data['status'] !== 'approved') {
            $user->status = $data['status'];
            $user->approved_at = null;
            $user->approved_by = null;
        }
    }

    $user->save();

    return redirect()->route('admin.sellers.index')->with('status', __('messages.profile_updated'));
}
```

- [ ] **Step 4: Create the edit view**

Create `resources/views/admin/sellers/edit.blade.php`:
```blade
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-brand-navy leading-tight">{{ __('messages.edit_profile') }} — {{ $seller->name }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white shadow-sm sm:rounded-lg p-6">
                <form method="POST" action="{{ route('admin.sellers.update', $seller) }}" class="space-y-5">
                    @csrf @method('PATCH')

                    <div>
                        <x-input-label for="name" :value="__('messages.name')" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full"
                                      :value="old('name', $seller->name)" required />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="email" :value="__('messages.email')" />
                        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full"
                                      :value="old('email', $seller->email)" required />
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="phone" :value="__('messages.phone')" />
                        <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full"
                                      :value="old('phone', $seller->phone)" required />
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>

                    {{-- DOB + live age --}}
                    <div x-data="{
                            dob: '{{ old('date_of_birth', optional($seller->date_of_birth)->format('Y-m-d')) }}',
                            get age() {
                                if (! this.dob) return '';
                                const b = new Date(this.dob), t = new Date();
                                let a = t.getFullYear() - b.getFullYear();
                                const m = t.getMonth() - b.getMonth();
                                if (m < 0 || (m === 0 && t.getDate() < b.getDate())) a--;
                                return a >= 0 ? a : '';
                            }
                         }" class="grid grid-cols-3 gap-4">
                        <div class="col-span-2">
                            <x-input-label for="date_of_birth" :value="__('messages.date_of_birth')" />
                            <x-text-input id="date_of_birth" name="date_of_birth" type="date" class="mt-1 block w-full"
                                          x-model="dob" max="{{ now()->subYears(18)->toDateString() }}" />
                            <x-input-error :messages="$errors->get('date_of_birth')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="age" :value="__('messages.age')" />
                            <x-text-input id="age" type="text" class="mt-1 block w-full bg-gray-100"
                                          disabled x-bind:value="age" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="status" :value="__('messages.status')" />
                        <select id="status" name="status" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            @foreach (['pending', 'approved', 'rejected'] as $s)
                                <option value="{{ $s }}" @selected(old('status', $seller->status) === $s)>{{ __('messages.status_'.$s) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="role" :value="__('messages.role')" />
                        <select id="role" name="role" class="mt-1 block w-full rounded-lg border-gray-300 text-sm">
                            <option value="seller" @selected(old('role', $seller->role) === 'seller')>{{ __('messages.role_seller') }}</option>
                            <option value="admin" @selected(old('role', $seller->role) === 'admin')>{{ __('messages.role_admin') }}</option>
                        </select>
                        <x-input-error :messages="$errors->get('role')" class="mt-2" />
                    </div>

                    @if ($seller->id === auth()->id())
                        <p class="text-xs text-amber-600">{{ __('messages.role') }} / {{ __('messages.status') }}: —</p>
                    @endif

                    <div class="flex items-center gap-3">
                        <button type="submit" class="bg-brand-blue hover:bg-blue-700 text-white font-semibold px-5 py-2.5 rounded-lg text-sm">
                            {{ __('messages.save') }}
                        </button>
                        <a href="{{ route('admin.sellers.index') }}" class="text-sm text-gray-500 underline">{{ __('messages.manage_sellers') }}</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
```

- [ ] **Step 5: Add the "Edit" link to the sellers table**

In `resources/views/admin/sellers/index.blade.php`, inside the actions `<div class="flex justify-end gap-2">`, add as the FIRST child (before the approve form):
```blade
<a href="{{ route('admin.sellers.edit', $seller) }}" class="text-brand-blue font-medium">{{ __('messages.edit') }}</a>
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, inside the `Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(...)` block, add:
```php
Route::get('/sellers/{user}/edit', [\App\Http\Controllers\Admin\AdminSellerController::class, 'edit'])->name('sellers.edit');
Route::patch('/sellers/{user}', [\App\Http\Controllers\Admin\AdminSellerController::class, 'update'])->name('sellers.update');
```
(These do not collide with the existing `/sellers/{user}/sponsor` or `/sellers/{user}/approve` routes.)

- [ ] **Step 7: Run tests**

Run: `php artisan test --filter=AdminProfileEditTest` then full suite.
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add -A
git commit -m "feat(admin): edit-profile action for sellers with DOB, status and role"
```

---

## Task 5: Build + final verification

- [ ] **Step 1: Build assets and run the full suite**

Run:
```bash
npm run build && php artisan test
```
Expected: assets compile cleanly; ALL tests pass.

- [ ] **Step 2: Commit (if the build produced asset changes)**

```bash
git add -A
git commit -m "chore: rebuild assets for profile edit form" || echo "nothing to commit"
```

---

## Final Verification

- [ ] `php artisan test` → all green.
- [ ] `npm run build` → no errors.
- [ ] **Live DB (MariaDB):** `php artisan migrate --force` (NEVER `migrate:fresh` — the live DB has real accounts).
- [ ] Manual walkthrough on `php artisan serve` as `admin@admin.com` / `admin`:
  1. `/admin/sellers` → click **Edit** on a seller → change name/phone, set a DOB → the **Age** field updates live → Save → row reflects changes.
  2. Set a seller's status to approved and role to admin via the form → verify it applies; set another back to pending → audit fields clear.
  3. Edit your own admin account → confirm role/status selects don't change your account, but name does.
  4. Register a new seller at `/register` → DOB is required and must be 18+.
  5. Toggle ES and confirm labels (Editar perfil, Fecha de nacimiento, Edad, Rol) localize.
