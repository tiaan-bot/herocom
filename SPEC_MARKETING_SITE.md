# SPEC — Marketing Site (Phase 1B, small pass)

> Read `CLAUDE.md` first. This spec covers the public marketing pages: Home (`/`), Products (`/products`), Contact (`/contact`).
> Visual reference: the Claude Design export in `design/marketing/` (Home.jsx, Products.jsx, Contact.jsx, Chrome.jsx, marketing.css). Reproduce its layout, copy and styling faithfully in our stack — do NOT import the export's code as-is.

## 1. Scope

**In scope**
- Three public Inertia/Vue pages: Home, Products, Contact
- Shared public layout (header/nav + footer) per `Chrome.jsx`
- Contact form → queued email to sales (no DB persistence)
- New brand tokens (Heroic Purple) for the public pages only

**Out of scope (do not build)**
- Public product search (portal catalog owns search)
- Live product data on the marketing Products page (static featured items per the design are fine for launch)
- Any change to portal pages/themes
- Email verification, newsletter, blog, analytics

## 2. Routes & controllers

| Route | Name | Controller | Notes |
|---|---|---|---|
| GET `/` | `marketing.home` | `Web\Marketing\HomeController@index` | replaces current root route |
| GET `/products` | `marketing.products` | `Web\Marketing\ProductsController@index` | static content |
| GET `/contact` | `marketing.contact` | `Web\Marketing\ContactController@show` | |
| POST `/contact` | `marketing.contact.send` | `Web\Marketing\ContactController@send` | rate-limited |

- All routes public, no auth middleware.
- Authenticated resellers visiting `/` still see the marketing page (nav adapts — see §4).
- Keep existing `/apply` and `/login` untouched; marketing CTAs link to them.

## 3. Pages (content source = design export)

### Home (`Home.jsx`)
Sections in order: hero ("Your Top ICT Distributor" + subhead + Apply/Browse CTAs), brand strip, stat strip (6+ brands, Credit & COD accounts, 48hr dispatch, 100% online ordering), "We are the intelligence…" section, "Everything you need to sell more, faster" feature cards, "Become a Herocom reseller" CTA band, footer.

### Products (`Products.jsx`)
Sections: catalogue hero with Sign in / Apply CTAs, 6 category cards (Surveillance, Networking, Power, Peripherals, Storage, More), 8 featured product cards with lock icon + "Sign in for trade pricing", brand chips, full-catalogue CTA band.
Featured items are hardcoded exactly as in the export.

### Contact (`Contact.jsx`)
Form: name, company, email, phone, message (all per export). Contact details beside it:
- Phone: **087 551 1485**
- Email: **sales@herocom.co.za**
- Address: **15 Desi Street, Middelburg, 1050**
Success state replaces/overlays the form after submit (per export behaviour).

### Copy rules
- Copy must match the export verbatim. NO "CGIC" anywhere.
- Hero headline renders lowercase via Smooth Circulars — that is intentional brand behaviour; do not "fix" the casing.

## 4. Shared layout (`Chrome.jsx`)

- `resources/js/Layouts/MarketingLayout.vue`
- Nav: logo (links `/`), Home, Products, Contact; right side: "Sign in" (ghost → `/login`) + "Apply" (primary → `/apply`).
- If user is authenticated reseller: replace Sign in/Apply with "Go to portal" → `/catalog`. If staff: → `/admin`.
- Footer per export: contact details, links (Products, Contact, Sign in, Apply, Terms & Conditions PDF, Privacy/POPI).
- Mobile: hamburger menu, all sections stack per export's responsive CSS.

## 5. Branding / styling

- Tokens (scoped to marketing pages, e.g. `marketing.css` layer or Tailwind theme extension under a `mkt-` prefix):
  - Primary: Heroic Purple `#733DA0`
  - Electric White `#FFFFFF`, dark grey/near-black accents per export CSS
- Fonts: self-host **Aspira** (body/headings) and **Smooth Circulars** (hero display only). Font files in `design/marketing/fonts/` — if absent, ask Tiaan before substituting. Fallback stack: a geometric sans (e.g. `Poppins, system-ui`).
- Do NOT change portal theme tokens in this pass. A separate portal re-theme to purple is a later task.
- Lucide icons: use `lucide-vue-next` equivalents of the `data-lucide` names in the export.

## 6. Contact form behaviour

- `StoreContactMessageRequest` (Form Request): name required|max:120; company nullable|max:160; email required|email; phone nullable|max:30; message required|max:2000.
- Honeypot field (hidden input, reject silently if filled) + rate limit `5/min per IP` via named limiter `marketing-contact`.
- On pass: dispatch queued mailable `ContactMessageReceived` to `sales@herocom.co.za` (config: `mail.marketing_contact_to`, env `MARKETING_CONTACT_TO`). Reply-To = submitter's email.
- No DB table. Log submissions via activitylog? **No** — keep it stateless; the email is the record.
- Inertia response: redirect back with flash `success`; Vue shows the success state.

## 7. Build passes

1. **Pass 1 — Layout & Home**: MarketingLayout, brand tokens, fonts, Home page. Acceptance: `/` renders pixel-close to export at desktop + mobile; CTAs route correctly; auth-aware nav works.
2. **Pass 2 — Products & Contact pages (static)**: both pages render per export; lock cards show "Sign in for trade pricing".
3. **Pass 3 — Contact form**: validation, honeypot, rate limit, queued mail. Acceptance: submit with worker running → email arrives (log mailer in dev); invalid input shows inline errors; 6th rapid submit is throttled.

Each pass: Pest tests + Pint + PHPStan L8 + vue-tsc green before review.

## 8. Tests (minimum)

- Feature: each GET route returns 200 with correct Inertia component.
- Feature: contact POST happy path queues mailable (Mail::fake / Queue assertions), validation errors, honeypot rejection, rate-limit 429.
- Feature: nav state for guest vs reseller vs staff.
- Assert "CGIC" does not appear in rendered Home/Products/Contact responses.

## 9. Gotchas

- `APP_URL` affects asset URLs — verify fonts load on `herocom.test`.
- Queued mail needs `queue:work` running locally (see CLAUDE.md).
- Existing root route may currently redirect to `/login` or welcome — replace cleanly, keep `/login` intact.
- Rate limiters live in cache; `php artisan cache:clear` resets a tripped throttle during testing.

## 10. Open questions for Tiaan (ask before assuming)

- Are Aspira/Smooth Circulars font files licensed for web embedding? If unsure, ship with fallback stack and flag.
- Terms & Conditions PDF location/URL for the footer link.
