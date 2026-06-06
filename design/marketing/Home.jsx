const { Button } = window.HerocomDesignSystem_7092d9;

function MktHome({ go }) {
  const brands = ["Hikvision", "Ubiquiti", "TP-Link", "APC", "Logitech", "Mecer"];
  const stats = [
    ["6+", "leading brands stocked"],
    ["Credit & COD", "accounts"],
    ["48hr", "national dispatch"],
    ["100%", "online trade ordering"],
  ];
  const pillars = [
    { icon: "waypoints", n: "01", h: "Synchronicity", b: "We align complex variables — stock, pricing, logistics — into a single, seamless flow." },
    { icon: "cpu", n: "02", h: "Intelligence", b: "Our approach is rooted in data-driven precision and architectural stability." },
    { icon: "trending-up", n: "03", h: "Expansion", b: "Infrastructure that lets our partners scale their reach without losing their focus." },
  ];
  const benefits = [
    { icon: "tag", h: "Trade pricing", b: "Tiered reseller pricing on every line, shown ex VAT the moment you sign in." },
    { icon: "boxes", h: "Live stock", b: "Real-time availability across the catalogue — no phone calls to check what is in." },
    { icon: "monitor-smartphone", h: "Online ordering", b: "Place and track orders from any device. Your cart, your history, always in sync." },
    { icon: "shield-check", h: "Credit & COD", b: "Apply once for a credit facility, or trade on COD upfront — whichever suits your business." },
  ];

  return (
    <div className="mkt">
      {/* Hero */}
      <section className="hero">
        <div className="hero__glow"></div>
        <div className="hero__inner">
          <div className="hero__copy">
            <span className="hero__eyebrow">The Distribution Nexus</span>
            <h1 className="hero__title display">Your Top ICT Distributor</h1>
            <p className="hero__lead">Herocom is the B2B electronics distributor where SA resellers get trade pricing, live stock and online ordering.</p>
            <div className="hero__cta">
              <Button as="a" href="/apply" size="lg">Apply for an account <i data-lucide="arrow-right"></i></Button>
              <Button size="lg" variant="outline" onClick={() => go("products")}>Browse products</Button>
            </div>
            <p className="hero__note">Credit &amp; COD accounts · approved in days, not weeks.</p>
          </div>
          <div className="hero__mark">
            <img src={(window.__resources && window.__resources.nexusMark) || "../../assets/logos/nexus-ico-1.png"} alt="The Herocom Nexus mark" />
          </div>
        </div>
      </section>

      {/* Brands strip */}
      <section className="brands">
        <span className="brands__label">Distributing the brands resellers ask for</span>
        <div className="brands__row">
          {brands.map((b) => <span key={b} className="brands__chip">{b}</span>)}
        </div>
      </section>

      {/* Stats */}
      <section className="stats">
        {stats.map(([n, l]) => (
          <div key={l} className="stats__item">
            <div className="stats__n">{n}</div>
            <div className="stats__l">{l}</div>
          </div>
        ))}
      </section>

      {/* Pillars */}
      <section className="pillars">
        <div className="pillars__head">
          <span className="eyebrow">Our DNA</span>
          <h2 className="section-title">We are the intelligence that holds the chain together.</h2>
        </div>
        <div className="pillars__grid">
          {pillars.map((p) => (
            <div key={p.h} className="pillar">
              <div className="pillar__icon"><i data-lucide={p.icon}></i></div>
              <span className="pillar__n">{p.n}</span>
              <h3 className="pillar__h">{p.h}</h3>
              <p className="pillar__b">{p.b}</p>
            </div>
          ))}
        </div>
      </section>

      {/* Benefits */}
      <section className="benefits">
        <div className="benefits__inner">
          <div className="benefits__head">
            <span className="eyebrow">For resellers</span>
            <h2 className="section-title">Everything you need to sell more, faster.</h2>
            <p className="benefits__sub">One approved account unlocks the catalogue, your pricing and the tools to order at trade.</p>
          </div>
          <div className="benefits__grid">
            {benefits.map((bn) => (
              <div key={bn.h} className="benefit">
                <div className="benefit__icon"><i data-lucide={bn.icon}></i></div>
                <div>
                  <h3 className="benefit__h">{bn.h}</h3>
                  <p className="benefit__b">{bn.b}</p>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* Dark CTA band */}
      <section className="band">
        <div className="band__glow"></div>
        <div className="band__inner">
          <div>
            <h2 className="band__title">Become a Herocom reseller.</h2>
            <p className="band__sub">Apply once. Get trade pricing, a live catalogue and online ordering — with flexible credit terms.</p>
          </div>
          <Button as="a" href="/apply" size="lg">Start your application <i data-lucide="arrow-right"></i></Button>
        </div>
      </section>
    </div>
  );
}

window.MktHome = MktHome;
