# Herocom Distribution Platform — Project Context

> This file is read by Claude Code at the start of every session.
> Keep it accurate, keep it lean. Update it when architectural decisions change.

---

## 1. Mission

A B2B distribution platform for **Herocom Distribution** (South Africa).
Resellers onboard, get vetted and credit-approved, then place orders for products sourced from our catalog. Zoho Books is the source of truth for products, inventory, customers, invoices, and payments. The platform handles the full reseller lifecycle: onboarding → catalog discovery → ordering → fulfilment → service & warranty.

Designed for SA launch with international expansion later — schema is multi-currency, multi-warehouse, multi-region-aware from day one.

---

## 2. Tech Stack

| Layer | Choice | Version |
|---|---|---|
| Language | PHP | 8.3+ |
| Framework | Laravel | 12.x |
| Database | PostgreSQL | 16 |
| Cache / Queue / Sessions | Redis | 7 |
| Queue worker | Laravel Horizon | latest |
| Admin panel | Filament | v4 |
| Reseller portal | Inertia.js + Vue 3 + Tailwind 4 | latest |
| UI components | shadcn-vue | latest |
| Search | Meilisearch | 1.x |
| Object storage | Cloudflare R2 (S3-compatible) | — |
| Auth | Laravel Fortify + 2FA | latest |
| Permissions | spatie/laravel-permission | latest |
| Audit log | spatie/laravel-activitylog | latest |
| Email | Postmark (transactional) + Mailgun inbound (tickets) | — |
| Testing | Pest | 3.x |
| Deployment | Laravel Forge → Hostinger VPS (Ubuntu) | — |
| Source of truth | Zoho Books (REST API + webhooks) | — |
| Payments | Zoho Books native PayFast integration | — |

> **Version note (changed from the original plan):** the original brief specified Laravel 11 / Filament v3 / Tailwind 3. Laravel 11 reached end of security support on 12 March 2026, so a new build should not start on it. We are on **Laravel 12** (supported into Feb 2027; the 12→13 upgrade is near-zero-effort). Moving off Laravel 11 also let us adopt **Filament v4** (stable since Aug 2025), which runs on **Tailwind 4** — this keeps the admin panel and the shadcn-vue reseller portal on a single Tailwind major. No architectural decisions changed; this is a version refresh only.
>
> - **Tailwind 4** uses CSS-first configuration (no `tailwind.config.js` by default). Configure via the `@theme` directive in CSS.
> - The Laravel 12 default install ships with **PHPUnit**. We will swap the test layer to **Pest 3** during Phase 0.
> - **Filament v4** has built-in panel authentication and 2FA. Fortify is still the auth layer for the **reseller portal** (Inertia/Vue). For the Filament admin panel, prefer Filament's native auth + 2FA rather than wiring Fortify into it.

---

## 2a. Local Development Environment

Development is on **Windows using Laragon** (the founder's existing, familiar setup, also used with Claude Code on other projects). Production remains an **Ubuntu VPS on Hostinger, managed via Laravel Forge**.

| Tool | Where | Notes |
|---|---|---|
| PHP 8.3.x | Laragon (`C:\laragon\bin\php\...`) | `pdo_pgsql` + `pgsql` extensions enabled |
| Composer 2.x | Laragon | — |
| Node + npm | Laragon (bundled) | Confirm enabled before front-end work |
| Redis | Laragon (bundled) | Confirm enabled before Horizon work |
| Terminal | Cmder (Laragon's Terminal button) | cmd-based; mind `^` escaping — quote Composer version constraints |
| PostgreSQL 16 | **Standalone Windows service** (EDB installer), `localhost:5432` | Runs independently of Laragon; superuser `postgres`; pgAdmin 4 installed |
| Project root | `C:\laragon\www\herocom` | Served at `http://herocom.test` (Laragon auto-vhost) |

**Dev/prod parity caveat:** local is Windows, production is Ubuntu. Watch for path-separator and case-sensitivity assumptions, and prefer Forge/Ubuntu-native process management (Supervisor for Horizon) on the server rather than mirroring Windows habits.

---

## 3. Architectural Principles

1. **Zoho is the source of truth** for products, inventory, customers, invoices, payments. Our DB is a local read-optimised replica + the system of record for things Zoho doesn't own (onboarding state, tickets, warranty claims, cart, sessions, audit history).
2. **Domain-oriented code organisation** under `app/Domain/*`. Each domain owns its models, services, actions, events, jobs, and policies.
3. **No business logic in controllers.** Controllers receive a Form Request, call a single Action class, return a response.
4. **Form Requests for all validation.** Never validate inside controllers or services.
5. **Services for orchestration. Actions for atomic operations.** A service may compose multiple actions.
6. **Events + listeners for cross-domain communication.** Order placement raises `OrderPlaced`; the Zoho domain listens and dispatches `PushOrderToZoho`.
7. **Every external call is queued.** Zoho API, Postmark, PayFast webhooks → Horizon jobs with retry + idempotency.
8. **Idempotency everywhere.** Every sync job carries an external ID; DB has unique constraints on `zoho_*_id` columns.
9. **Audit everything that matters.** Onboarding decisions, role changes, credit limit changes, order status changes, warranty decisions — all via activitylog.
10. **International-ready schema.** Every monetary column has a sibling `currency` column. Every address has `country_code`. Every stock row has `warehouse_id`.

---

## 4. Directory Structure

```
app/
├── Domain/
│   ├── Onboarding/         # Companies, applications, documents, approvals
│   │   ├── Models/
│   │   ├── Actions/
│   │   ├── Services/
│   │   ├── Events/
│   │   ├── Listeners/
│   │   ├── Jobs/
│   │   └── Policies/
│   ├── Catalog/            # Products, categories, inventory, pricing tiers
│   ├── Ordering/           # Cart, orders, order items, reservations
│   ├── Billing/            # Invoices, payments, statements, credit
│   ├── Ticketing/          # Tickets, messages, warranty claims, RMA
│   ├── Identity/           # Users, roles, permissions, sessions
│   └── Shared/             # Cross-domain value objects, enums
├── Filament/               # Admin panel resources, pages, widgets
├── Http/
│   ├── Controllers/
│   │   ├── Web/            # Inertia controllers for reseller portal
│   │   └── Api/            # API endpoints (future POS integration, mobile)
│   ├── Requests/           # Form Request classes
│   └── Middleware/
├── Services/
│   └── Zoho/               # ZohoClient, ProductSync, OrderSync, etc.
│       ├── ZohoClient.php
│       ├── ProductSyncService.php
│       ├── OrderSyncService.php
│       ├── InvoiceSyncService.php
│       └── Webhooks/
├── Providers/
└── Support/                # Helpers, traits, macros

database/
├── migrations/
├── factories/
└── seeders/

resources/
├── js/
│   ├── Pages/              # Inertia pages
│   ├── Components/         # Vue components
│   ├── Layouts/
│   └── lib/                # shadcn-vue config
├── views/                  # Blade (marketing site + emails only)
└── css/

tests/
├── Feature/
└── Unit/
```

**Rule of thumb:** if you're adding a new model, ask "which domain owns this?" and place it there. Cross-domain interaction happens via events, never direct imports between domains.

---

## 5. Coding Conventions

### PHP
- **Strict types declared** at the top of every file: `declare(strict_types=1);`
- **PSR-12** + Laravel Pint defaults. Run `vendor/bin/pint` before commit.
- **Constructor property promotion** for all DTOs and services.
- **Readonly properties** where the value doesn't change after construction.
- **Enums** for any fixed set of values (status, role, type). Backed enums with explicit values, never magic strings.
- **Final classes** by default for services, actions, and listeners.
- **Return type declarations** mandatory on every method.
- **Avoid facades inside domain code.** Inject dependencies. Facades acceptable in controllers and Artisan commands.
- **No business logic in models.** Models contain relationships, casts, scopes. Logic goes to Actions/Services.

### Naming
- Actions: `VerbNounAction` — e.g. `ApproveOnboardingApplicationAction`, `PushOrderToZohoAction`
- Services: `NounService` — e.g. `ProductSyncService`, `CreditCheckService`
- Events: past tense — `OrderPlaced`, `ApplicationApproved`
- Jobs: `VerbNoun` — e.g. `SyncZohoProducts`, `SendOnboardingApprovalEmail`
- Form Requests: `VerbNounRequest` — e.g. `StoreOrderRequest`

### Vue / Inertia
- Composition API only, `<script setup lang="ts">` always.
- TypeScript everywhere. Define props with `defineProps<T>()`.
- Components colocated by page when single-use; shared components in `resources/js/Components`.
- Tailwind utility-first (Tailwind 4, CSS-first config). Use `cn()` helper for conditional classes.
- shadcn-vue primitives for inputs, dialogs, dropdowns, tables — do not reinvent.

### Database
- Migrations are **never edited after they ship**. New change = new migration.
- Foreign keys explicit, with `onDelete` and `onUpdate` declared.
- Money stored as `decimal(15,4)` with sibling `currency char(3)`.
- Timestamps default. Soft deletes only where business reason exists (orders, tickets — never for products which sync from Zoho).
- Index every foreign key and every column used in `WHERE` or `ORDER BY`.
- Use Postgres-specific features where helpful: JSONB for flexible specs, partial indexes, generated columns.

---

## 6. Domain Glossary

| Term | Meaning |
|---|---|
| **Reseller** | A `Company` whose `status = approved`. Only resellers see products and can order. |
| **Company** | The legal business entity that onboards. One company has many `User` accounts. |
| **Onboarding Application** | The submission a Company makes including all documents (CIPC, VAT, bank confirmation, proof of address, credit application). |
| **Tier** | Pricing band: `bronze`, `silver`, `gold`, `platinum`. Determined by monthly spend. Drives `tier_pricing` rows. |
| **Credit Terms** | A Company's payment arrangement: `eft_upfront` or `on_account` (with `credit_limit` and `credit_terms_days`). |
| **CGIC** | Credit Guarantee Insurance Corporation — SA credit insurer; their decision drives whether a Company gets `on_account` or is restricted to `eft_upfront`. |
| **Sales Order** | A Zoho Books entity. We push our `Order` to Zoho as a Sales Order. |
| **RMA** | Return Merchandise Authorisation — generated number tied to a warranty claim. |
| **Reservation** | When a reseller adds an item to cart, we hold stock for 15 minutes via `inventory_reservations`. Expires automatically. (Phase 3) |
| **Rebate** | Tier-based discount paid back to high-volume resellers, calculated monthly. (Phase 3) |

---

## 7. Zoho Integration Rules

**Authentication:** OAuth 2.0, refresh token stored encrypted in `zoho_tokens` table. `ZohoClient` handles auto-refresh.

**Direction of truth:**

| Entity | Source of truth | Sync direction |
|---|---|---|
| Products / Items | Zoho | Zoho → Laravel only |
| Inventory levels | Zoho | Zoho → Laravel only |
| Customers (Companies in Zoho) | Mixed | Laravel creates on approval → pushed to Zoho; subsequent edits sync both ways with last-write-wins on `updated_at` |
| Sales Orders | Laravel | Laravel → Zoho only |
| Invoices | Zoho | Zoho → Laravel only |
| Payments | Zoho | Zoho → Laravel only |

**Webhooks in (Zoho → us):**
- Endpoint: `POST /webhooks/zoho/{event}`
- Verify HMAC signature against `ZOHO_WEBHOOK_SECRET`
- Dispatch `Process{Entity}WebhookJob` to Horizon — never process inline
- Store raw payload in `webhook_logs` table for debugging/replay

**Polling fallback:** scheduled job every 60s polls Zoho for `items` and `salesorders` updated in the last 5 minutes. Catches missed webhooks. Idempotent via `zoho_*_id` unique constraints.

**Outbound (us → Zoho):**
- All writes go through `Services/Zoho/*` services, never raw HTTP from controllers/actions
- Every outbound write is a queued job with `tries=5`, `backoff=[10, 60, 300, 900, 3600]`
- On final failure, raise `ZohoSyncFailed` event → admin notification

**Idempotency:** every outbound job checks for an existing `zoho_*_id` before creating. Every inbound webhook upserts on `zoho_*_id`.

**Rate limiting:** Zoho's limit is 5000 calls/day. Wrap `ZohoClient` with a Redis-backed rate limiter; queue jobs back off on 429.

---

## 8. Permissions & Roles

Roles managed via `spatie/laravel-permission`. Initial roles:

**Internal (Herocom staff):**
- `super_admin` — everything
- `sales_admin` — onboarding approval, orders, tickets
- `finance_admin` — credit limits, invoices, payments
- `warranty_admin` — warranty claims, RMA management
- `support_agent` — tickets read/respond, no approval rights
- `viewer` — read-only across admin panel

**External (reseller users):**
- `reseller_owner` — full access to their Company's account, can invite users
- `reseller_buyer` — can browse, order, log tickets
- `reseller_viewer` — read-only (e.g. accountant viewing statements)

**Audit rule:** every role assignment, role change, and any action by `super_admin` against another user is logged via activitylog with reason field required.

---

## 9. Testing Standards

- **Pest** is the test framework. No PHPUnit syntax in new tests. (The default Laravel 12 install ships PHPUnit; swap to Pest in Phase 0.)
- **Feature tests** for every HTTP endpoint and every Filament resource.
- **Unit tests** for every Action and every Service method with branching logic.
- **HTTP fakes** for all Zoho/Postmark/PayFast interactions — never hit real APIs in tests.
- **Database:** use `RefreshDatabase` trait with Postgres in CI.
- **Factories** for every model. Tests build their own state, never rely on seeders.
- **Coverage target:** 80%+ on `app/Domain/*` and `app/Services/Zoho/*`. Controllers and Filament resources covered by feature tests.
- Run `composer test` before every commit. CI blocks merge if tests fail.

---

## 10. Common Commands

```bash
# Local dev (run from C:\laragon\www\herocom in the Laragon terminal)
php artisan serve               # or just use http://herocom.test (Laragon auto-vhost)
npm run dev
php artisan horizon
php artisan schedule:work

# Quality
vendor/bin/pint                 # format
vendor/bin/phpstan analyse      # static analysis (Larastan, level 8)
composer test                   # run pest

# Database (PostgreSQL on localhost:5432, db: herocom)
php artisan migrate
php artisan migrate:fresh --seed
php artisan db:seed --class=DevelopmentSeeder

# Zoho
php artisan zoho:auth                   # one-time OAuth handshake
php artisan zoho:sync products          # manual full product sync
php artisan zoho:sync inventory         # manual stock pull
php artisan zoho:webhook-replay {id}    # replay a logged webhook

# Filament (v4)
php artisan make:filament-resource {Name}
php artisan filament:upgrade
```

---

## 11. Anti-Patterns — Do Not Do These

- ❌ Don't query Zoho from a controller or view. Always go through a service, always queued for writes.
- ❌ Don't store prices as floats. Always `decimal(15,4)`.
- ❌ Don't put validation in services — Form Requests only.
- ❌ Don't import a model from another domain directly. Use events, or expose a read-model service.
- ❌ Don't seed production data through migrations. Use seeders, and only the `ProductionSeeder` runs in prod.
- ❌ Don't expose Zoho IDs in URLs or to resellers. Use UUIDs for public-facing identifiers on orders/tickets.
- ❌ Don't write business logic in Blade or Vue components. Compute in the controller, pass as props.
- ❌ Don't disable CSRF, rate limiting, or 2FA "temporarily" — find another way.

---

## 12. Environment Variables (reference)

```
APP_NAME="Herocom Distribution"
APP_ENV=local                  # production on the VPS
APP_URL=http://herocom.test    # https://herocom.co.za in production

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=herocom
DB_USERNAME=postgres
DB_PASSWORD=...                # local: the postgres superuser password set at install
REDIS_HOST=127.0.0.1

ZOHO_CLIENT_ID=...
ZOHO_CLIENT_SECRET=...
ZOHO_REGION=eu                 # or .com / .in depending on Herocom's Zoho org
ZOHO_ORGANIZATION_ID=...
ZOHO_WEBHOOK_SECRET=...

MEILISEARCH_HOST=...
MEILISEARCH_KEY=...

POSTMARK_TOKEN=...
MAILGUN_INBOUND_SIGNING_KEY=... # for ticket email-in

R2_ACCESS_KEY_ID=...
R2_SECRET_ACCESS_KEY=...
R2_BUCKET=herocom-uploads
R2_ENDPOINT=https://...r2.cloudflarestorage.com
```

---

## 13. Phased Build Roadmap (high-level)

- **Phase 0** — Foundation: repo, CI, staging, Filament shell, roles, audit, Zoho OAuth.
- **Phase 1 — MVP launch:** marketing site, onboarding (with admin approval + CGIC manual step), product catalog, tiered pricing, cart, checkout, order → Zoho push, invoice display, Zoho-PayFast payment redirect.
- **Phase 2 — Service layer:** tickets (with email in/out), warranty claims, RMA, reseller statements.
- **Phase 3 — Differentiators:** WhatsApp ordering, smart reorder, live stock reservation, rebate engine, reseller P&L view, embedded stock finance, POS API.
- **Phase 4 — International:** multi-currency activation, multi-warehouse, multi-language.

When working on a task, identify which Phase it belongs to and don't pull in scope from later phases unless explicitly asked.

---

## 14. When Unsure

- If a decision isn't covered here, ask before implementing.
- Prefer the simplest solution that doesn't paint us into a corner internationally.
- If you're about to write code that duplicates something Zoho already does, stop and reconsider — Zoho is the source of truth.
- If you're about to put logic in two places, extract it into a single Action.

---

*Last updated: end of environment-setup session — stack refreshed to Laravel 12 / Filament v4 / Tailwind 4; local dev environment is Laragon on Windows + standalone PostgreSQL 16.*
