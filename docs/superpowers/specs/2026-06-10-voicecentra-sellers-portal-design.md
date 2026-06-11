# VoiceCentra Sellers Portal — Design

**Date:** 2026-06-10
**Status:** Approved (pending spec review)

## Overview

A Laravel web application — the **VoiceCentra Sellers Portal** — that recruits and
manages independent sellers for VoiceCentra, a **voice AI phone assistant**. The
public landing page invites potential sellers to join the team and earn commission
selling the product. Registered sellers go through an admin-approval gate before
gaining access to their dashboard. Admins manage the seller roster.

### Goals
- A modern, professional, on-brand landing page that converts visitors into seller sign-ups.
- Self-service seller registration with an admin approval workflow.
- Two distinct user types: **seller** and **admin**, with separate dashboards.
- Bilingual UI (English + Spanish).

### Non-goals (MVP)
- Sales pipeline, leads, commissions tracking (dashboards are a minimal shell for now).
- Email verification (admin approval is the gate).
- Admin self-registration or in-app role promotion.
- Public marketing site beyond the single landing page.

## Brand

- **Product:** VoiceCentra — a voice AI phone assistant (24/7 AI receptionist).
- **Portal name:** "VoiceCentra for Sellers".
- **Colors:** brand blue `#154CB6`, deep navy `#0A1130`, on white/light supporting tones.
- **Logos:** stored in `brand-assets/` (`voicecentra_icon_color.svg`,
  `voicecentra_wordmark_color.svg`, `voicecentra_logo_stacked_color.svg`).
- **Landing direction:** "Bold Navy" — dark navy hero, high-tech and premium, with a
  voice-waveform motif. Login, register, and dashboards inherit the same style.

## Tech Stack

- **Laravel 11** (latest), PHP 8.3.
- **Laravel Breeze** — Blade + Tailwind CSS (login, register, password reset scaffolding).
- **MySQL** — uses an existing MySQL server the user already has; connection details
  go in `.env` at setup time (host, port, database, username, password). Nothing hardcoded.
- **i18n** — Laravel localization, session-stored locale, nav language switcher (EN/ES).
- **Testing** — Pest feature tests (TDD).

> Note: No local MySQL server or Docker on the dev machine; the `pdo_mysql` PHP
> extension is present. The user will supply credentials for their existing MySQL server.

## Data Model

Extends the Breeze default `users` table:

| Column | Type | Purpose |
|---|---|---|
| `role` | enum(`seller`,`admin`), default `seller` | distinguishes the two user types |
| `status` | enum(`pending`,`approved`,`rejected`), default `pending` | seller approval state (admins always `approved`) |
| `phone` | string, nullable | seller profile field |
| `approved_at` | timestamp, nullable | when an admin approved |
| `approved_by` | FK → `users.id`, nullable | which admin approved |

`User` model helpers: `isAdmin()`, `isSeller()`, `isApproved()`.

## Roles & Access Control

- **`EnsureUserIsAdmin` middleware** — guards all `/admin/*` routes.
- **`EnsureSellerApproved` middleware** — guards the seller dashboard; unapproved
  sellers are redirected to the pending-approval page.
- **`SetLocale` middleware** — applies the session locale to each request.
- `/dashboard` is a smart redirect: admin → admin dashboard; approved seller → seller
  dashboard; pending/rejected seller → pending page.

## Registration & Approval Flow

1. Visitor clicks **"Become a seller"** → Breeze register form (name, email, phone, password).
2. Account created as `role=seller`, `status=pending`, then logged in.
3. They land on a friendly, on-brand **"Application pending"** screen — no dashboard access.
4. An **admin** sees them in the sellers table and clicks **Approve** or **Reject**
   (sets `status`, `approved_at`, `approved_by`).
5. Approved seller reaches their dashboard on next login/refresh.
6. **Admins are never self-registered** — one admin is created via a seeder;
   registration always produces sellers.

## Routes & Pages

| Route | Access | Description |
|---|---|---|
| `/` | public | Landing page (Bold Navy) |
| `/register`, `/login`, `/forgot-password`, … | public | Breeze auth, restyled to brand |
| `/dashboard` | auth | smart redirect by role/status |
| `/pending` | auth seller | status holding screen — shows a "pending review" message for `pending` sellers and a distinct "not approved" message for `rejected` sellers |
| `/seller/dashboard` | approved seller | welcome + profile card (minimal shell) |
| `/admin/dashboard` | admin | summary stat cards |
| `/admin/sellers` | admin | sellers table with status filter + Approve/Reject |
| `/profile` | auth | Breeze profile edit (name, email, phone, password) |
| `/lang/{locale}` | any | switch EN/ES, stores locale in session |

## Landing Page Sections (Bold Navy)

1. **Sticky nav** — logo, EN/ES toggle, "Log in", "Become a seller" button.
2. **Hero** — headline + subhead + two CTAs + voice-waveform motif.
3. **How it works** — 3 steps: Sign up → Get approved → Start selling & earning.
4. **What you'll sell** — the voice AI phone assistant: 24/7 answering, never miss a
   call, books appointments, multilingual.
5. **Why sell with VoiceCentra** — commission on every deal, growing market,
   sales materials & support.
6. **Final CTA band** — "Become a seller".
7. **Footer** — logo, minimal links, copyright.

## Dashboards (Minimal Shell)

- **Seller dashboard** — branded welcome ("Welcome, {name}"), a profile summary card,
  and a placeholder panel ("Sales tools coming soon") so it doesn't feel empty.
- **Admin dashboard** — small stat cards (total / pending / approved seller counts)
  plus the sellers management table. Approve/Reject are plain POST form buttons
  (no Livewire) that update status and redirect back with a flash message.

## Internationalization

- `lang/en/*.php` and `lang/es/*.php` hold all UI strings (landing, auth, dashboards).
- `SetLocale` middleware applies the session locale.
- Nav language toggle switches between English and Spanish.

## Seeding

- `DatabaseSeeder` creates:
  - **One admin** — email/password from `.env` (with sane defaults).
  - **Demo sellers** — one `pending`, one `approved` — so the admin table isn't empty
    on first run.

## Testing (TDD, Pest feature tests)

- Registration creates a `pending` seller.
- Pending seller is blocked from `/seller/dashboard` → redirected to `/pending`.
- Admin can approve a seller; the approved seller then reaches the dashboard.
- Non-admin is blocked from `/admin/*`.
- Locale switch changes the rendered language.
- Plus Breeze's bundled authentication tests.
