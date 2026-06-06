const { Button } = window.HerocomDesignSystem_7092d9;

function MktNav({ go, route }) {
  const links = [
    { key: "home", label: "Home" },
    { key: "products", label: "Products" },
    { key: "contact", label: "Contact" },
  ];
  return (
    <header className="mnav">
      <div className="mnav__bar">
        <a className="mnav__logo" onClick={() => go("home")}>
          <img src={(window.__resources && window.__resources.wordmarkDark) || "../../assets/logos/herocom-wordmark.png"} alt="Herocom Distribution" />
        </a>
        <nav className="mnav__links">
          {links.map((l) => (
            <a key={l.key} className={route === l.key ? "is-active" : ""} onClick={() => go(l.key)}>{l.label}</a>
          ))}
        </nav>
        <div className="mnav__actions">
          <Button as="a" href="/login" variant="ghost" size="sm">Sign in</Button>
          <Button as="a" href="/apply" size="sm">Apply</Button>
        </div>
      </div>
    </header>
  );
}

function MktFooter({ go }) {
  const cols = [
    { h: "Distribution", items: [["Products", () => go("products")], ["Brands", () => go("products")], ["Trade pricing", () => go("products")]] },
    { h: "Resellers", items: [["Apply", "/apply"], ["Sign in", "/login"], ["Credit & COD", "/apply"]] },
    { h: "Company", items: [["The Nexus", () => go("home")], ["Contact", () => go("contact")], ["POPIA", () => go("contact")]] },
  ];
  return (
    <footer className="mfoot">
      <div className="mfoot__inner">
        <div className="mfoot__brand">
          <img src={(window.__resources && window.__resources.wordmarkLight) || "../../assets/logos/herocom-wordmark-light.png"} alt="Herocom Distribution" />
          <p>The Distribution Nexus. B2B electronics distribution for South African resellers.</p>
        </div>
        <div className="mfoot__cols">
          {cols.map((c) => (
            <div key={c.h} className="mfoot__col">
              <h4>{c.h}</h4>
              {c.items.map(([label, target]) => (
                typeof target === "string"
                  ? <a key={label} href={target}>{label}</a>
                  : <a key={label} onClick={target}>{label}</a>
              ))}
            </div>
          ))}
        </div>
      </div>
      <div className="mfoot__base">
        <span>© 2026 Herocom Distribution (Pty) Ltd</span>
        <span>Your information is processed in line with POPIA.</span>
      </div>
    </footer>
  );
}

window.MktNav = MktNav;
window.MktFooter = MktFooter;
