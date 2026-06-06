const { Button, Input, Textarea, FormField } = window.HerocomDesignSystem_7092d9;

function MktContact() {
  const [sent, setSent] = React.useState(false);

  return (
    <div className="contact">
      <section className="contacthero">
        <span className="eyebrow">Contact</span>
        <h1 className="section-title contacthero__title">Talk to the Nexus.</h1>
        <p className="contacthero__lead">Become a reseller, check stock, or sort out an account — the right team is one message away.</p>
      </section>

      <section className="contactbody">
        <div className="contactbody__grid">
          {/* Form */}
          <div className="cform">
            {sent ? (
              <div className="cform__sent">
                <div className="cform__senticon"><i data-lucide="check"></i></div>
                <h2 className="cform__senth">Message received</h2>
                <p>Thank you for reaching out. A member of our team will be in touch within one business day.</p>
                <Button variant="outline" onClick={() => setSent(false)}>Send another message</Button>
              </div>
            ) : (
              <form className="cform__form" onSubmit={(e) => { e.preventDefault(); setSent(true); }}>
                <h2 className="cform__title">Send us a message</h2>
                <div className="cform__row">
                  <FormField label="Name" required><Input placeholder="Your full name" /></FormField>
                  <FormField label="Company"><Input placeholder="Business name" /></FormField>
                </div>
                <div className="cform__row">
                  <FormField label="Email" required><Input type="email" placeholder="you@business.co.za" /></FormField>
                  <FormField label="Phone"><Input placeholder="0XX XXX XXXX" /></FormField>
                </div>
                <FormField label="Message" required>
                  <Textarea rows={5} placeholder="Tell us how we can help…" />
                </FormField>
                <Button type="submit" className="cform__submit">Send message <i data-lucide="arrow-right"></i></Button>
                <p className="cform__legal">Your information is processed in line with POPIA.</p>
              </form>
            )}
          </div>

          {/* Details */}
          <aside className="cdetails">
            <div className="cdetails__channels">
              <div className="channel">
                <div className="channel__icon"><i data-lucide="phone"></i></div>
                <div>
                  <h3 className="channel__h">Phone</h3>
                  <a className="channel__d" href="tel:0875511485">087 551 1485</a>
                </div>
              </div>
              <div className="channel">
                <div className="channel__icon"><i data-lucide="mail"></i></div>
                <div>
                  <h3 className="channel__h">Email</h3>
                  <a className="channel__d" href="mailto:sales@herocom.co.za">sales@herocom.co.za</a>
                </div>
              </div>
            </div>

            <div className="cdetails__branch">
              <h3 className="cdetails__bh"><i data-lucide="map-pin"></i> Head office</h3>
              <p>15 Desi Street<br />Middelburg<br />1050</p>
              <h3 className="cdetails__bh"><i data-lucide="clock"></i> Trading hours</h3>
              <p>Monday – Friday · 08:00 – 17:00<br />Collections by appointment</p>
            </div>

            <div className="cdetails__apply">
              <p>Ready to order? Skip the queue and apply for a reseller account.</p>
              <Button as="a" href="/apply" variant="secondary" size="sm">Apply now</Button>
            </div>
          </aside>
        </div>
      </section>
    </div>
  );
}

window.MktContact = MktContact;
