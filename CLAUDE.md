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
| Language | PHP | 8.4 (dev on 8.4.22) |
| Framework | Laravel | 12.x |
| Database | PostgreSQL | 16 |
| Cache / Queue / Sessions | Redis | 7 |
| Queue worker | Laravel Horizon | latest |
| Admin panel | Filament | v4 |
| Reseller portal | Inertia 2 (hand-wired) + Vue 3 + Tailwind 4 | latest |
| UI components | shadcn-vue | latest |
| Search | Meilisearch | 1.x |
| Object storage | Cloudflare R2 (S3-compatible) | — |
| Auth | Laravel Fortify + 2FA | latest |
| Permissions | spatie/laravel-permission | latest |
| Audit log | spatie/laravel-activitylog | latest |
| Email | Postmark (transactional) + Mailgun inbound (tickets) | — |
| Testing | Pest | 4.x (on PHPUnit 12) |
| Deployment | Laravel Forge → Hostinger VPS (Ubuntu) | — |
| Source of truth | Zoho Books (REST API + webhooks) | — |
| Payments | Zoho Books native PayFast integration | — |

> **Version note (changed from the original plan):** the original brief specified Laravel 11 / Filament v3 / Tailwind 3. Laravel 11 reached end of security support on 12 March 2026, so a new build should not start on it. We are on **Laravel 12** (supported into Feb 2027; the 12→13 upgrade is near-zero-effort). Moving off Laravel 11 also let us adopt **Filament v4** (stable since Aug 2025), which runs on **Tailwind 4** — this keeps the admin panel and the shadcn-vue reseller portal on a single Tailwind major. No architectural decisions changed; this is a version refresh only.
>
> - **Tailwind 4** uses CSS-first configuration (no `tailwind.config.js` by default). Configure via the `@theme` directive in CSS.
> - The test layer was swapped from the default PHPUnit to **Pest 4** in Phase 0 (PHPUnit removed as a top-level dep; Pest pulls in PHPUnit 12). Plugins installed: laravel, arch, mutate, profanity.
> - **Filament v4** has built-in panel authentication and 2FA. Fortify is still the auth layer for the **reseller portal** (Inertia/Vue). For the Filament admin panel, prefer Filament's native auth + 2FA rather than wiring Fortify into it.

---

## 2a. Local Development Environment

Development is on **Windows using Laragon** (the founder's existing, familiar setup, also used with Claude Code on other projects). Production remains an **Ubuntu VPS on Hostinger, managed via Laravel Forge**.

| Tool | Where | Notes |
|---|---|---|
| PHP 8.4.22 | Laragon (`C:\laragon\bin\php\...`) | Thread Safe / VS17 / x64. Extensions: `pdo_pgsql`, `pgsql`, `zip`. No `redis` extension — see Redis note. |
| Composer 2.9.4 | Laragon | — |
| Node v22 + npm 10 | Laragon (bundled) | Confirm enabled before front-end work |
| Redis | Laragon (bundled) | `REDIS_CLIENT=predis` on dev (pure-PHP, no extension), `phpredis` in production. See Redis note below. |
| Terminal | Cmder (Laragon's Terminal button) | cmd-based; mind `^` escaping — always quote Composer version constraints, e.g. `composer require pkg:"^4.0"` (bare cmd strips the caret and pins an exact version) |
| PostgreSQL 16 | **Standalone Windows service** (EDB installer), `localhost:5432` | Runs independently of Laragon; superuser `postgres`; pgAdmin 4 installed |
| Project root | `C:\laragon\www\herocom` | Served at `http://herocom.test` (Laragon auto-vhost) |

**Dev/prod parity caveat:** local is Windows, production is Ubuntu. Watch for path-separator and case-sensitivity assumptions, and prefer Forge/Ubuntu-native process management (Supervisor for Horizon) on the server rather than mirroring Windows habits.

**Redis client (dev vs prod):** set via the `REDIS_CLIENT` env var — `predis` on dev, `phpredis` in production. The official phpredis Windows DLL stops at PHP 8.1, so dev uses pure-PHP **predis** (no extension); the Ubuntu/Forge prod box installs **phpredis** cleanly. **Sessions, cache, and the queue all run on Redis** (`SESSION_DRIVER=redis`, `CACHE_STORE=redis`) — on predis in dev. The legacy `sessions`/`cache` DB tables are harmless leftovers.

**Horizon does not run on Windows** (it needs `ext-pcntl` / `ext-posix`, which don't exist on Windows). `composer.json` carries a `config.platform` override for those two extensions so the package still installs locally. **Locally, process jobs with `php artisan queue:work`, NOT `php artisan horizon`** — Horizon runs as the supervisor in production only.

**Windows/cmd gotcha:** always quote Composer version constraints containing `^`, e.g. `composer require pkg:"^4.0"` — bare `cmd` strips the caret and pins an exact version.

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
│   └── Shared/             # Cross-domain value objects, enums, concerns
│       ├── Concerns/       # e.g. HasUuid
│       └── Zoho/           # ZohoClient, ZohoException, Models/ZohoToken (shared Zoho infra)
├── Filament/               # Admin panel resources, pages, widgets
├── Http/
│   ├── Controllers/
│   │   ├── Web/            # Inertia controllers for reseller portal
│   │   └── Api/            # API endpoints (future POS integration, mobile)
│   ├── Requests/           # Form Request classes
│   └── Middleware/
├── Providers/
└── Support/                # Helpers, traits, macros

# Zoho: the shared client lives in app/Domain/Shared/Zoho (ZohoClient, ZohoException,
# Models/ZohoToken). Domain-specific sync (products, orders, invoices) + webhook
# handling live in the owning domain (e.g. app/Domain/Catalog/Services), not a central
# app/Services/Zoho — persistence belongs to each domain.

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
- The front-end is **hand-wired Inertia 2 + Vue 3 + Tailwind 4 (not the official Vue starter kit)** — the starter kit ships open self-registration auth, which conflicts with the gated, approved-resellers-only B2B model. Inertia pages live in `resources/js/Pages/`.
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

### Audit logging (spatie/laravel-activitylog v5)
> v5's API differs from most docs/tutorials, which still show v4. Use the v5 forms:
- Trait: `Spatie\Activitylog\Models\Concerns\LogsActivity` (**not** `...\Traits\LogsActivity`).
- Options: `Spatie\Activitylog\Support\LogOptions` (**not** `Spatie\Activitylog\LogOptions`).
- Attribute diffs are stored in the **`attribute_changes`** column, not `properties` (`properties` now holds custom properties only).
- Use `dontLogEmptyChanges()` (**not** `dontSubmitEmptyLogs()`).
- **Never log `password` or 2FA secrets.** Scope each model's `getActivitylogOptions()` with `logOnly([...])` / `logExcept([...])`.
- `User` carries `LogsActivity` as the first worked example.

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

## 6a. Onboarding & legal model

Domain root: `app/Domain/Onboarding` (Models, Enums, plus Actions/Services/Events/etc. as the domain grows).

- **Two trading modes** (from the Standard T&Cs): **COD Sale Agreement** = `eft_upfront`; **CF Sale Agreement** = `on_account`, which must be secured by a Credit Guarantee (CGIC). COD is the default; credit is discretionary and can be withdrawn (reverts to COD, clause 7.6).
- **Onboarding is one flow** branched by `account_type_requested` (`cod` | `credit`). The credit branch collects principals, banking, credit requirements, turnover and legal disclosures; requires `bank_confirmation` + `proof_of_address` + `deed_of_surety`; and runs a **manual CGIC step**.
- **Credit terms set on approval**: cod → `eft_upfront`; credit → `on_account` with limit/terms set by finance (`manage_company_credit`). A credit application may not be approved unless `cgic_status = approved`.
- **Deed of Suretyship**: SA law requires an *advanced* electronic signature for an electronic suretyship — an ordinary e-sign is invalid and would make the surety unenforceable. Phase 1 stores a wet-ink scan as the `deed_of_surety` document. **Never ship an ordinary-e-sign surety.** AES via an accredited provider is deferred (Phase 3).
- **POPIA**: onboarding holds sensitive PII (SA ID numbers, bank details, residential addresses, director info). Documents on a **private** R2 bucket (`r2` disk) with signed-URL access only; encrypt `onboarding_principals.id_number` and `onboarding_applications.cgic_payload` at rest (Laravel `encrypted` cast → opaque/non-queryable by design); audit access. Capture consent (`terms_accepted_at` + `terms_version`, `popia_consent_at`, `credit_enquiry_consent_at`) immutably.
- **Never log** ID numbers, bank account numbers, passwords, or 2FA secrets via activitylog. Onboarding models scope `getActivitylogOptions()` accordingly (`id_number` and `cgic_payload` are excluded).
- **Public identifiers**: Company / OnboardingApplication / OnboardingDocument expose a `uuid` route key (`HasUuid` in `app/Domain/Shared/Concerns`). Never expose the internal id or `zoho_customer_id` in URLs.
- **Authorization**: the full role → permission matrix is seeded by `RolePermissionSeeder` (idempotent — `firstOrCreate` per permission, `syncPermissions` per role). Onboarding abilities: `view/process/approve_onboarding_applications` (sales_admin), `manage_company_credit` (finance_admin). **super_admin is granted nothing explicitly** — it bypasses every check via a `Gate::before` in `AppServiceProvider` (returns `true` for super_admin, `null` otherwise). New permissions are added to `RolePermissionSeeder`, not assigned to super_admin.

### Onboarding admin (Filament)

- **Two resources**: `OnboardingApplicationResource` (the review/approve workflow) and a minimal `CompanyResource` (finance credit). Both live under `app/Filament/Resources`; navigation group "Onboarding".
- **Applications are never admin-created or free-edited** (`canCreate(): false`, no edit page). Admins only *act* via gated header/row actions that call the domain Actions — there is **no business logic in Filament**. Each maps to an Action: `RequestApplicationInformationAction`, `RecordCgicOutcomeAction`, `Approve`/`RejectOnboardingApplicationAction`, `VerifyOnboardingDocumentAction`, `SetCompanyCreditAction`.
- **Two-party flow**: finance records CGIC + sets credit (`manage_company_credit`); sales verifies docs + approves/rejects (`process_` / `approve_onboarding_applications`). **Approve is disabled** (tooltip "Awaiting CGIC approval") for credit apps until `cgic_status = approved`; the Action also enforces it server-side and the page catches `OnboardingDecisionException` into a danger notification.
- **PII in the admin**: documents served via short-lived signed URLs (`GenerateOnboardingDocumentUrlAction` → `temporaryUrl` on the per-document disk, audited); `id_number` masked with an audited "Reveal ID" action (`RevealPrincipalIdAction`); `cgic_payload` shown only to `manage_company_credit` via an audited action (`ViewCgicPayloadAction`). Reveal/CGIC/download access all write activitylog entries.
- **Document disk is env-driven** (`config/onboarding.php` → `ONBOARDING_DOCUMENT_DISK`, default `r2`): production uses R2; set to `local` in dev (serve=true, so `temporaryUrl` works) until R2 creds are wired.

### Public application form

- **Public route `GET/POST /apply`** (+ `GET /apply/success`) — a thin `Web\OnboardingApplicationController` that reuses `StoreOnboardingApplicationRequest` (validation + `toData()`) and `SubmitOnboardingApplicationAction`. No business logic in the controller. POST is `throttle:5,60` and the request carries a **honeypot** (`website` field, `prohibited` rule). T&Cs version comes from `config('onboarding.terms.version')`.
- **No email verification in Phase 1** — on submit we create the application + send an `ApplicationReceivedNotification` (on-demand mail) and redirect to the success page. Spam control = throttle + honeypot only. Real verification (column + token + route) is a deferred fast-follow.
- **Multi-step Vue/Inertia wizard** (`resources/js/Pages/Onboarding/Apply.vue`) branched by `account_type_requested`; credit adds Principals + Financials steps. Wizard state is in-component only (no draft/save-progress; single-session). Client mirrors required-field/doc rules for UX but the Form Request is the source of truth.
- **Front-end stack is TypeScript** (the "TypeScript everywhere" standard applies): shadcn-vue (Reka UI, `typescript: true`) under `resources/js/components/ui`, `@/` alias wired in both `vite.config.js` and `tsconfig.json`, `cn()` in `resources/js/lib/utils.ts`, lucide icons. Entry is `resources/js/app.ts`. Pages/components use `<script setup lang="ts">`; type the Inertia page props and `useForm<T>` payloads. Run `npm run typecheck` (`vue-tsc --noEmit`) — keep it green. Reference the lowercase `@/components/...` path (case-sensitive in prod).
- **Document uploads are server-side** to the env-driven private disk (`ONBOARDING_DOCUMENT_DISK`); no presigned/direct-to-R2 uploads yet.

---

## 6b. Catalog domain

Domain root: `app/Domain/Catalog`. The `products` table is a **read-optimised one-way mirror** of Zoho Books items — Zoho is the source of truth; **no product editing anywhere** in our admin or portal.

- **Sync** (`SyncProductsFromZoho` + `SyncZohoProducts` job): upsert by the unique `zoho_item_id` (idempotent); a full sync marks items missing from Zoho **inactive** (never deleted). `zoho:sync-products {--full}` for manual runs; scheduled incremental every 30 min + nightly full. Queued, retry-safe. Inactive items stay (so order history can reference them) but are **hidden from the portal**.
- **Sync admin**: Filament `CatalogSync` page (gated `manage_catalog_sync`) shows counts/last-synced and a "Sync now" button. `manage_catalog_sync` is currently held only by super_admin (no role grant in the matrix yet).
- **Pricing**: computed at render by `CompanyPriceCalculator` (list `rate` − company `discount_percent`), **ex VAT**, never stored, never client-trusted. The Phase 3 tier engine replaces only this class.
- **Stock**: shown as a band (`StockBand`: in/low/out, low ≤ `config('catalog.low_stock_threshold')`, default 5), never the exact quantity.
- **Search**: Postgres `LOWER(col) LIKE` (portable case-insensitive) over name/sku/brand — Scout + Meilisearch is a deferred drop-in for large catalogues (10k+ SKUs).
- **Catalog access** (`/catalog`, `/catalog/{uuid}`): `auth` + `EnsureApprovedReseller` (reseller must belong to an `approved` company; internal staff with no company pass) + `can:view_catalog`. No cart yet (Ordering pass).

---

## 6c. Reseller portal auth

Domain root for auth actions: `app/Domain/Identity`; thin Web controllers under `app/Http/Controllers/Web/Auth`.

- **Hand-wired Inertia auth** (no starter kit / no Fortify — the starter kit's open self-registration conflicts with the gated B2B model). **No self-registration ever**: users exist only via the onboarding approval flow (`reseller_owner`) or admin creation. The login page links to `/apply`.
- **Single `web` guard** for everyone. After login: **company users → `/catalog`, internal staff (no company) → `/admin`**. `redirectGuestsTo('/login')`.
- **Set-password** (first-time): the welcome email links to a **signed, 7-day route** `GET /set-password/{user:uuid}`; success sets the password, logs the user in, redirects to `/catalog`. The link is **single-purpose** (guarded by `users.password_set_at`); an expired/invalid signature shows a friendly page with a **throttled re-send**. Logic lives in `SetUserPasswordAction`.
- **Login / logout / forgot / reset**: standard `web` session auth + the Password broker; the reset page reuses the set-password `PasswordFields` component. Login is throttled (`Password::defaults()`, standard "remember me"). **No 2FA in Phase 1** (Filament admin keeps its own 2FA).
- **Users have a `uuid`** (for the signed set-password route) and `password_set_at`.

---

## 6d. Ordering domain

Domain root: `app/Domain/Ordering`. Cart → checkout → Order → Zoho Sales Order. Zoho stays the source of truth for the commercial documents; the Order is the portal-side record that produces a Zoho SO.

- **Cart**: DB-backed, **one open cart per user** (partial unique index + `GetOrCreateOpenCart`), re-priced on read via `CompanyPriceCalculator`; inactive-product lines are flagged and excluded from checkout. Mutations gated by `place_orders`.
- **Orders snapshot at placement** (`PlaceOrderAction`, in a transaction): immutable unit prices (list + discounted), line names/skus, and delivery address; `order_number` = `HD-######`; the cart is marked converted; `OrderPlaced` is raised. Catalog price changes never mutate a placed order. **No soft deletes** — orders are permanent.
- **Statuses** (T&Cs offer/acceptance model): `placed → accepted → rejected/cancelled`. Acceptance is a manual Filament action in Phase 1.
- **Zoho push state is separate from order status**: `zoho_push_status` (pending/pushed/failed) + unique `zoho_salesorder_id` (idempotency anchor). The `OrderPlaced` listeners are queued + idempotent: `PushOrderToZoho` (creates the Zoho **customer first** if the company lacks `zoho_customer_id`, via the shared `EnsureZohoCustomerAction`, then the SO at the **discounted** unit price) and `SendOrderConfirmationEmail`. Failed pushes retry and surface in Filament with a **Retry** action.
- **No stock reservation, no credit-limit enforcement** in Phase 1 (Billing pass may add available-credit checks). Portal shows **ex-VAT**; Zoho computes VAT on the SO/invoice.
- **Access**: portal `/orders`(+`/{uuid}`) gated `auth` + approved reseller + `view_orders`, scoped to the user's company (`OrderPolicy`). Filament `OrderResource` (internal, no create/edit) — Accept/Reject/Retry-push gated `manage_orders`.

---

## 7. Zoho Integration Rules

**Authentication:** **Self-client (server-to-server) OAuth 2.0** — no redirect flow. A one-time `zoho:authorize {grantToken}` exchanges a self-client grant token for a refresh token; `ZohoClient` (`app/Domain/Shared/Zoho`) auto-refreshes access tokens thereafter. `zoho_tokens` is a single-row, **encrypted** store (`refresh_token`, `access_token`); tokens are never logged. Token refresh is wrapped in a cache lock so parallel queued jobs don't double-refresh.

**Foundation vs persistence:** the Zoho foundation proves connectivity only — `zoho:authorize` (one-time setup) and `zoho:ping` (fetches the org + a page of items). **It does not persist Zoho data; persistence lives in each domain — Catalog first.** Methods: `getOrganization()`, `listItems()`, and a generic `request()` others build on.

**Config & resilience:** region-driven base URLs from `config/zoho.php` (`ZOHO_REGION` → `accounts_domain` + `api_domain`; never hard-code a data centre) plus `organization_id` attached to Books calls. `ZohoClient` retries on `429`/`5xx` with exponential backoff, honours `Retry-After`, has a sane timeout, and throws a typed `ZohoException` on non-retryable failures. **Never log tokens or secrets.**

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
- All writes go through `ZohoClient` (`app/Domain/Shared/Zoho`) and the owning domain's sync services, never raw HTTP from controllers/actions
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

- **Pest 4** is the test framework (runs on PHPUnit 12; PHPUnit was removed as a top-level dep in Phase 0). No PHPUnit syntax in new tests.
- **Feature tests** for every HTTP endpoint and every Filament resource.
- **Unit tests** for every Action and every Service method with branching logic.
- **HTTP fakes** for all Zoho/Postmark/PayFast interactions — never hit real APIs in tests.
- **Database:** use `RefreshDatabase` trait with Postgres in CI.
- **Factories** for every model. Tests build their own state, never rely on seeders.
- **Coverage target:** 80%+ on `app/Domain/*` (includes the shared Zoho client in `app/Domain/Shared/Zoho`). Controllers and Filament resources covered by feature tests.
- Run `composer test` before every commit. CI blocks merge if tests fail.

---

## 10. Common Commands

```bash
# Local dev (run from C:\laragon\www\herocom in the Laragon terminal)
php artisan serve               # or just use http://herocom.test (Laragon auto-vhost)
npm run dev
php artisan queue:work          # process jobs LOCALLY (Horizon can't run on Windows — prod only)
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
php artisan zoho:authorize {grantToken} # one-time self-client grant → refresh token
php artisan zoho:ping                    # prove connectivity (org + a page of items)
# Future (Catalog+ passes): zoho:sync products / inventory, zoho:webhook-replay {id}

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
REDIS_CLIENT=predis            # predis on dev (no extension); phpredis in production

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

*Last updated: Phase 0 complete (per HEROCOM_PHASE_1_HANDOVER.md §7) — confirmed stack is Laravel 12 / Filament v4 / Tailwind 4 / Pest 4 / PHP 8.4; Redis via predis on dev + phpredis in prod; Horizon is prod-only (Windows can't run it, use `queue:work` locally); activitylog v5 API notes added; front-end is hand-wired Inertia 2 + Vue 3 (not the starter kit). Next: Phase 1.*
