# Marketing Site — UI kit

A high-fidelity, pixel-considered recreation of the **public Herocom marketing site** — a 3-page brand site built on the Heroic-Purple design system and driven by the brand CI ("The Distribution Nexus").

## Run
Open `index.html`. Loads `../../styles.css` + `marketing.css`, the standalone component runtime (`../../lib/ds-runtime.js`), Lucide, and the page scripts. Starts on **Home**; the top nav routes between Home / Products / Contact. "Apply" and "Sign in" link out to the existing reseller portal (`/apply`, `/login`).

## Pages
- **Home** (`Home.jsx`) — hero (Smooth Circulars display headline + Nexus mark + soft purple glow), brands strip, stats, the three brand pillars (Synchronicity / Intelligence / Expansion, verbatim from the CI), a reseller value-props section, and a dark charcoal CTA band.
- **Products** (`Products.jsx`) — public catalogue overview: category cards, featured lines (pricing gated behind sign-in), authorised brands, CTA. No trade pricing shown to the public.
- **Contact** (`Contact.jsx`) — enquiry form (built from the DS form primitives, with a submitted success state), contact channels, head-office address and trading hours.
- **Chrome** (`Chrome.jsx`) — sticky public nav + dark footer.

## Files
| File | Surface |
|---|---|
| `index.html` | Entry |
| `Chrome.jsx` | Nav + footer |
| `Home.jsx` | Homepage |
| `Products.jsx` | Products overview |
| `Contact.jsx` | Contact + enquiry form |
| `app.jsx` | Route state |
| `marketing.css` | Kit-specific layout, token-driven |

## Type roles (as applied here)
- **Smooth Circulars** — the hero headline only (`.display`).
- **Aspira Bold** — every section heading (`.section-title`, card titles).
- **Aspira Regular** — body, leads, captions.

## Notes
- Composes DS primitives (`Button`, `Input`, `Select`, `Textarea`, `FormField`, `Badge`) — no re-implemented components.
- Pillar copy is verbatim from the CI; South African English, no exclamation marks.
- The 3D liquid Nexus mark is used per the CI's "Digital / High-Impact" rule; the flat wordmark sits in the nav, the light wordmark in the footer.
- **Placeholder content to replace during development:** product imagery uses the `Package`-icon tile. Contact details are real — phone 087 551 1485, email sales@herocom.co.za, head office 15 Desi Street, Middelburg, 1050.
