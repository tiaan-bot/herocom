<script setup lang="ts">
import { ref } from 'vue'
import { Head } from '@inertiajs/vue3'
import { ArrowRight, Check, Clock, Mail, MapPin, Phone } from 'lucide-vue-next'
import MarketingLayout from '@/Layouts/MarketingLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import FormField from '@/components/FormField.vue'

// Pass 2 is static: this is the export's client-side success toggle (no network).
// Pass 3 replaces it with a real Inertia POST → validation, honeypot, rate limit, queued mail.
const sent = ref(false)
</script>

<template>
  <Head title="Contact — Herocom Distribution" />
  <MarketingLayout>
    <div class="contact">
      <section class="contacthero">
        <span class="eyebrow">Contact</span>
        <h1 class="section-title contacthero__title">Talk to the Nexus.</h1>
        <p class="contacthero__lead">Become a reseller, check stock, or sort out an account — the right team is one message away.</p>
      </section>

      <section class="contactbody">
        <div class="contactbody__grid">
          <!-- Form -->
          <div class="cform">
            <div v-if="sent" class="cform__sent">
              <div class="cform__senticon"><Check /></div>
              <h2 class="cform__senth">Message received</h2>
              <p>Thank you for reaching out. A member of our team will be in touch within one business day.</p>
              <Button variant="outline" @click="sent = false">Send another message</Button>
            </div>
            <form v-else class="cform__form" @submit.prevent="sent = true">
              <h2 class="cform__title">Send us a message</h2>
              <div class="cform__row">
                <FormField label="Name" required><Input placeholder="Your full name" /></FormField>
                <FormField label="Company"><Input placeholder="Business name" /></FormField>
              </div>
              <div class="cform__row">
                <FormField label="Email" required><Input type="email" placeholder="you@business.co.za" /></FormField>
                <FormField label="Phone"><Input placeholder="0XX XXX XXXX" /></FormField>
              </div>
              <FormField label="Message" required>
                <Textarea :rows="5" placeholder="Tell us how we can help…" />
              </FormField>
              <Button type="submit" class="cform__submit">Send message <ArrowRight /></Button>
              <p class="cform__legal">Your information is processed in line with POPIA.</p>
            </form>
          </div>

          <!-- Details -->
          <aside class="cdetails">
            <div class="cdetails__channels">
              <div class="channel">
                <div class="channel__icon"><Phone /></div>
                <div>
                  <h3 class="channel__h">Phone</h3>
                  <a class="channel__d" href="tel:0875511485">087 551 1485</a>
                </div>
              </div>
              <div class="channel">
                <div class="channel__icon"><Mail /></div>
                <div>
                  <h3 class="channel__h">Email</h3>
                  <a class="channel__d" href="mailto:sales@herocom.co.za">sales@herocom.co.za</a>
                </div>
              </div>
            </div>

            <div class="cdetails__branch">
              <h3 class="cdetails__bh"><MapPin /> Head office</h3>
              <p>15 Desi Street<br>Middelburg<br>1050</p>
              <h3 class="cdetails__bh"><Clock /> Trading hours</h3>
              <p>Monday – Friday · 08:00 – 17:00<br>Collections by appointment</p>
            </div>

            <div class="cdetails__apply">
              <p>Ready to order? Skip the queue and apply for a reseller account.</p>
              <Button as="a" href="/apply" variant="secondary" size="sm">Apply now</Button>
            </div>
          </aside>
        </div>
      </section>
    </div>
  </MarketingLayout>
</template>
