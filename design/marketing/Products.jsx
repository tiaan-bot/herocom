const { Button, Badge } = window.HerocomDesignSystem_7092d9;

function MktProducts({ go }) {
  const categories = [
    { icon: "cctv", h: "Surveillance", b: "IP cameras, NVRs and recorders", n: "120+ lines" },
    { icon: "network", h: "Networking", b: "Switches, access points and routers", n: "180+ lines" },
    { icon: "plug-zap", h: "Power", b: "UPS, surge protection and PDUs", n: "60+ lines" },
    { icon: "keyboard", h: "Peripherals", b: "Keyboards, mice and accessories", n: "90+ lines" },
    { icon: "hard-drive", h: "Storage", b: "SSDs, drives and surveillance storage", n: "70+ lines" },
    { icon: "boxes", h: "More", b: "New lines added every week", n: "Browse all" },
  ];
  const featured = [
    { name: "Hikvision 2MP IR Dome Camera", sku: "DS-2CD1123G0E", brand: "Hikvision", cat: "Surveillance" },
    { name: "Ubiquiti UniFi U6 Lite Access Point", sku: "U6-LITE", brand: "Ubiquiti", cat: "Networking" },
    { name: "APC Back-UPS 700VA", sku: "BX700U-GR", brand: "APC", cat: "Power" },
    { name: "TP-Link 8-Port Gigabit Switch", sku: "TL-SG108", brand: "TP-Link", cat: "Networking" },
    { name: "Mecer 480GB 2.5\" SATA SSD", sku: "M-SSD480", brand: "Mecer", cat: "Storage" },
    { name: "Hikvision 8-Channel 4K NVR", sku: "DS-7608NI-Q1", brand: "Hikvision", cat: "Surveillance" },
    { name: "Logitech MK270 Wireless Combo", sku: "920-004523", brand: "Logitech", cat: "Peripherals" },
    { name: "Ubiquiti UniFi FlexHD Access Point", sku: "UAP-FLEXHD", brand: "Ubiquiti", cat: "Networking" },
  ];
  const brands = ["Hikvision", "Ubiquiti", "TP-Link", "APC", "Logitech", "Mecer"];

  return (
    <div className="prod">
      {/* Header */}
      <section className="prodhero">
        <div className="prodhero__inner">
          <span className="eyebrow">Catalogue</span>
          <h1 className="section-title prodhero__title">The electronics resellers actually sell.</h1>
          <p className="prodhero__lead">Surveillance, networking, power and peripherals from the brands your customers trust. Sign in for live stock and your trade pricing.</p>
          <div className="prodhero__cta">
            <Button as="a" href="/login">Sign in to view pricing</Button>
            <Button as="a" href="/apply" variant="outline">Apply for an account</Button>
          </div>
        </div>
      </section>

      {/* Categories */}
      <section className="cats">
        <div className="cats__grid">
          {categories.map((c) => (
            <div key={c.h} className="catcard">
              <div className="catcard__icon"><i data-lucide={c.icon}></i></div>
              <div className="catcard__body">
                <h3 className="catcard__h">{c.h}</h3>
                <p className="catcard__b">{c.b}</p>
              </div>
              <span className="catcard__n">{c.n}</span>
            </div>
          ))}
        </div>
      </section>

      {/* Featured */}
      <section className="featured">
        <div className="featured__head">
          <h2 className="section-title">Featured lines</h2>
          <span className="featured__note">Pricing shown to approved resellers</span>
        </div>
        <div className="featured__grid">
          {featured.map((p) => (
            <div key={p.sku} className="fcard">
              <div className="fcard__media"><i data-lucide="package"></i></div>
              <Badge tone="neutral" className="fcard__cat">{p.cat}</Badge>
              <h3 className="fcard__name">{p.name}</h3>
              <p className="fcard__meta">{p.sku} · {p.brand}</p>
              <div className="fcard__price">
                <i data-lucide="lock"></i> Sign in for trade pricing
              </div>
            </div>
          ))}
        </div>
      </section>

      {/* Brands */}
      <section className="brands brands--solo">
        <span className="brands__label">Authorised distributor for</span>
        <div className="brands__row">
          {brands.map((b) => <span key={b} className="brands__chip">{b}</span>)}
        </div>
      </section>

      {/* CTA band */}
      <section className="band">
        <div className="band__glow"></div>
        <div className="band__inner">
          <div>
            <h2 className="band__title">See the full catalogue.</h2>
            <p className="band__sub">Approved resellers get live stock, trade pricing and online ordering across every line.</p>
          </div>
          <Button as="a" href="/apply" size="lg">Apply for an account <i data-lucide="arrow-right"></i></Button>
        </div>
      </section>
    </div>
  );
}

window.MktProducts = MktProducts;
