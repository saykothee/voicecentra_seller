# Change-Sponsor Dropdown — Design

**Date:** 2026-06-12
**Status:** Approved (pending spec review)
**Builds on:** `2026-06-11-commission-system-design.md`

## Overview

On the admin change-sponsor page (`/admin/sellers/{user}/sponsor`), replace the
free-text email input with a dropdown of eligible sponsors, so admins pick from
valid choices instead of typing emails that may fail validation.

## Behavior

- `<select name="sponsor_email">` — same field name and value type (email) as
  today, so `AdminSellerController::updateSponsor` and all existing tests remain
  untouched. Server-side validation stays as defense in depth.
- First option: "None (top level)" with an empty value → makes the seller top-level.
- One option per eligible sponsor, labeled `Name (email)`, value = email.
- The current sponsor is pre-selected; otherwise the "None" option is.

## Eligibility (computed in `editSponsor`)

Approved sellers, excluding:
1. the seller being edited,
2. anyone in the seller's own subtree (cycle prevention),
3. anyone whose `depth + subtreeHeight(seller) > config('commissions.max_depth')`
   (chain would exceed 10).

Implementation: one `SellerTree::subtreeUsers($seller->id)` call (excluded ids) +
one `SellerTree::subtreeHeight($seller)` call, then filter the approved-sellers
query in PHP. The dropdown only offers choices that will pass `updateSponsor`'s
validation.

## i18n

Two new keys, added to BOTH catalogs (parity test enforces this):
- `new_sponsor` — EN "New sponsor", ES "Nuevo patrocinador" (field label)
- `none_top_level` — EN "None (top level)", ES "Ninguno (primer nivel)" (first option)

The old `new_sponsor_email` key stays in the catalogs (harmless, keeps parity).

## Testing

One new Pest test in `tests/Feature/SponsorChangeTest.php`: the edit page
- shows an eligible approved seller in the dropdown,
- excludes the seller themself, one of their descendants, a pending seller, and
  an over-depth candidate (a depth-9 sponsor when the moved seller has height 2).

Existing `updateSponsor` tests are unchanged and must stay green.
