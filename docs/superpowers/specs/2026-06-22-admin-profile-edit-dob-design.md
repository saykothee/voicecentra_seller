# Admin Seller Profile Editing + Date of Birth — Design

**Date:** 2026-06-22
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-11-commission-system-design.md`

## Overview

Add an admin "Edit profile" action on `/admin/sellers` that edits all of a user's
fields (name, email, phone, date of birth, status, role) from one form, and add a
new `date_of_birth` field captured at registration. The edit form shows a
read-only, live-computed Age beside the date-of-birth input.

## Database

- Add `date_of_birth` DATE, nullable, after `phone` on `users` (existing users
  have none). Cast `'date_of_birth' => 'date'`. Add to `$fillable` (ordinary
  profile field, like `phone`).
- `User` gets a computed accessor `age()`: returns `date_of_birth?->age` (Carbon),
  or `null` when no DOB is set.

## Registration

`resources/views/auth/register.blade.php` + `RegisteredUserController::store`:
add a **required** date-of-birth input. Validation:
`['required', 'date', 'after:1900-01-01', 'before_or_equal:<today−18y>']`
(must be a real past date and at least 18 years old). The 18-year cutoff is
computed as `now()->subYears(18)->toDateString()`. Stored via the existing
explicit `User::create([...])` array.

## Admin Edit

- New actions-column link **Edit** in `resources/views/admin/sellers/index.blade.php`
  → `GET /admin/sellers/{user}/edit` → `AdminSellerController::edit`
  → `PATCH /admin/sellers/{user}` → `AdminSellerController::update`.
- Route param `{user}` is any user (no `isSeller` guard) so a user remains editable
  after a role change; the page is reached from the sellers list. Gated by the
  existing `admin` middleware.
- Form fields:
  - **Name** (required, string, max 255)
  - **Email** (required, email, max 255, unique ignoring this user)
  - **Phone** (required, string, max 30)
  - **Date of birth** (nullable on edit — legacy users may lack one — but if
    provided: `date`, `after:1900-01-01`, `before_or_equal:<today−18y>`)
  - **Age** — disabled input beside DOB; server-renders `$user->age`, and an
    Alpine `x-data` recomputes it live as the admin changes the DOB.
  - **Status** dropdown: pending / approved / rejected
  - **Role** dropdown: seller / admin
- Persistence: name/email/phone/date_of_birth via explicit assignment (DOB and the
  others set on the model, then `save()`). `role` and `status` are deliberately
  non-fillable, so they are set by explicit assignment too. Status transitions
  mirror the approve/reject flow:
  - new status `approved` and was not approved → set `approved_at = now()`,
    `approved_by = acting admin id`.
  - new status not `approved` → set `approved_at = null`, `approved_by = null`.
- **Self-lockout guard:** if `$user->id === auth()->id()`, the `role` and `status`
  inputs are ignored on update (name/email/phone/DOB still save), so an admin can't
  demote or suspend their own account here.

## i18n

New keys in BOTH `lang/en/messages.php` and `lang/es/messages.php` (parity test
enforces this): `edit`, `edit_profile`, `date_of_birth`, `age`, `role`,
`role_seller`, `role_admin`, `profile_updated`. Status option labels reuse the
existing `status_pending` / `status_approved` / `status_rejected` keys.

EN values: Edit / Edit profile / Date of birth / Age / Role / Seller / Admin /
"Profile updated.". ES values: Editar / Editar perfil / Fecha de nacimiento / Edad
/ Rol / Vendedor / Administrador / "Perfil actualizado.".

## Testing (Pest)

- Admin can open the edit page for a seller (200, sees the fields).
- Admin update saves name, email, phone, and date_of_birth.
- Admin can change role and status; setting status→approved stamps `approved_at`
  and `approved_by`; moving away clears them.
- Email uniqueness ignores the edited user (saving the same email succeeds).
- A future / under-18 date of birth is rejected (both on the admin form and at
  registration).
- Registration requires date_of_birth and stores it.
- The `age` accessor computes correctly and returns null when DOB is unset.
- Self-edit: an admin editing their own account cannot change their own role or
  status (those inputs are ignored); other fields still save.
- A non-admin is forbidden from the edit and update routes.

## Out of scope

- Adding DOB to the seller's own `/profile` page (not requested).
- Touching tree fields (`parent_id`/`depth`) when a seller becomes an admin —
  admins are simply excluded from network queries already.
