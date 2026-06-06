# SPEC — Portal Re-theme + Application PDF \& Signatures (Phase 1B, pre-go-live)

> Read `CLAUDE.md` first. Two work streams, built as separate passes. Brand reference: `design/marketing/marketing.css` tokens, assets in `public/images/brand/`, fonts in `public/fonts/marketing/`.

## Stream A — Portal re-theme (purple CI)

### Scope

Re-theme all non-marketing surfaces to the Heroic Purple brand so applicants/resellers see one consistent identity:

1. Auth pages: login, forgot/reset password
2. Reseller onboarding flow (`/apply`, all 5 steps)
3. Reseller portal layout (catalog, cart, orders, invoices)
4. Filament admin: brand colour + logo only (Filament panel `->colors(\['primary' => ...])` and `->brandLogo()`) — no deep restyling

### Rules

* Promote the brand tokens from `mkt-`-scoped to shared root tokens consumed by both marketing and portal; primary `#733DA0`, hover/dark variants per marketing.css purple scale.
* Fonts: Aspira for portal body/headings (reuse self-hosted @font-face). No Smooth Circulars in portal except logo imagery.
* Logos: `wordmark-full-color.png` on light headers, `wordmark-white.png` on dark/purple surfaces, `nexus-mark.png` as compact mark (auth card header, favicons).
* Replace the black "H" placeholder mark + black buttons on auth/onboarding with purple equivalents.
* Keep ALL existing functionality and tests passing — this is styling only. No copy changes (CGIC wording on credit card stays as-is).
* Favicon + `<title>` branding sitewide.

### Pass A acceptance

* Login, apply (all steps), portal pages, Filament login visually consistent with marketing site.
* Full gates green; zero functional test changes needed (style-only).

## Stream B — Application PDF + signature capture

### B1. Signature capture (Option A — in-form)

* New onboarding step "Declaration \& signature" inserted before Review (or merged into Review — match the flow's UX, builder's call, flag choice).
* Signature pad component (canvas-based, e.g. `signature\_pad` npm lib): draw with mouse/touch; Clear + Undo; also allow typed-name fallback rendered in a script font, with a toggle.
* Capture per signer:

  * **COD applications:** 1 signature — the applicant (authorised signatory). Fields: full name, capacity/title, date (auto), signature image.
  * **Credit applications:** applicant signatory signature as above, PLUS a declaration block listing each principal entered in the Company step with name + ID; capture ONE drawn signature from the present signatory and render the others as "to be signed on the Deed of Suretyship" note. (Multi-party remote signing is out of scope for v1 — the Deed of Suretyship remains a printed/uploaded document as today.)
* Store: signature as PNG (base64 → file) on Cloudflare R2 disk alongside other onboarding documents; DB columns on the application: `signed\_by\_name`, `signed\_by\_capacity`, `signed\_at`, `signature\_path`.
* Legal text above pad: declaration that information is true/complete, T\&Cs acceptance (link to standard T\&Cs), POPIA consent. Checkbox required before signing enabled.

### B2. PDF generation

* On application submission (existing submit event/action), queued job `GenerateApplicationPdf`:

  * Renders a PDF replicating the structure of the relevant paper form: `HEROCOM\_\_Reseller\_Application\_Form\_2.pdf` (COD) or `HEROCOM\_\_Credit\_Reseller\_Application\_Form\_2.pdf` (credit) — same sections/fields, populated from the application data, with the captured signature image + name/capacity/date in the signature block.
  * Header: full-colour wordmark; footer: contact details + POPIA line.
  * Library: spatie/laravel-pdf or barryvdh/laravel-dompdf — builder picks per CLAUDE.md conventions; flag choice. Blade template per form type.
* Attach: save PDF to R2 in the application's document folder; create a document record (same model used by uploaded onboarding docs) typed `application\_form`, visible in the Filament application's Documents section alongside uploads.
* Failure handling: job retries (3), failure logged + flagged on the application record so admins see "PDF pending/failed"; regenerate action in Filament.

### B3. Tests

* Feature: submission dispatches GenerateApplicationPdf; job creates document record + file (fake storage).
* Feature: signature step validation (declaration checkbox required, signature required, typed fallback works).
* PDF content smoke test: rendered HTML/Blade contains key fields (company name, reg no, signatory, account type) — assert on the view, not pixel output.
* Filament: document of type application\_form listed; regenerate action dispatches job.

### Build order

1. Pass A (re-theme) — visual, low risk
2. Pass B1 (signature step)
3. Pass B2+B3 (PDF + tests)
### Confirmed decisions
- Email the signed PDF to the applicant: YES — attach to the existing submission confirmation email.

Signature: drawn-only. No typed-name fallback — remove the toggle from B1.

