<script setup lang="ts">
import type { Component } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import {
  ArrowRight,
  Boxes,
  Cctv,
  HardDrive,
  Keyboard,
  Lock,
  Network,
  Package,
  PlugZap,
} from 'lucide-vue-next'
import MarketingLayout from '@/Layouts/MarketingLayout.vue'
import { Button } from '@/components/ui/button'

// Category browsing requires sign-in, so each card links to /login; the catch-all
// "More" card sends prospective resellers to /apply instead.
interface Category { icon: Component, h: string, b: string, n: string, href: string, aria: string }
const categories: Category[] = [
  { icon: Cctv, h: 'Surveillance', b: 'IP cameras, NVRs and recorders', n: '120+ lines', href: '/login', aria: 'Surveillance — sign in to browse' },
  { icon: Network, h: 'Networking', b: 'Switches, access points and routers', n: '180+ lines', href: '/login', aria: 'Networking — sign in to browse' },
  { icon: PlugZap, h: 'Power', b: 'UPS, surge protection and PDUs', n: '60+ lines', href: '/login', aria: 'Power — sign in to browse' },
  { icon: Keyboard, h: 'Peripherals', b: 'Keyboards, mice and accessories', n: '90+ lines', href: '/login', aria: 'Peripherals — sign in to browse' },
  { icon: HardDrive, h: 'Storage', b: 'SSDs, drives and surveillance storage', n: '70+ lines', href: '/login', aria: 'Storage — sign in to browse' },
  { icon: Boxes, h: 'More', b: 'New lines added every week', n: 'Browse all', href: '/apply', aria: 'Browse all — apply for a reseller account' },
]

interface Featured { name: string, sku: string, brand: string, cat: string }
const featured: Featured[] = [
  { name: 'Hikvision 2MP IR Dome Camera', sku: 'DS-2CD1123G0E', brand: 'Hikvision', cat: 'Surveillance' },
  { name: 'Ubiquiti UniFi U6 Lite Access Point', sku: 'U6-LITE', brand: 'Ubiquiti', cat: 'Networking' },
  { name: 'APC Back-UPS 700VA', sku: 'BX700U-GR', brand: 'APC', cat: 'Power' },
  { name: 'TP-Link 8-Port Gigabit Switch', sku: 'TL-SG108', brand: 'TP-Link', cat: 'Networking' },
  { name: 'Mecer 480GB 2.5" SATA SSD', sku: 'M-SSD480', brand: 'Mecer', cat: 'Storage' },
  { name: 'Hikvision 8-Channel 4K NVR', sku: 'DS-7608NI-Q1', brand: 'Hikvision', cat: 'Surveillance' },
  { name: 'Logitech MK270 Wireless Combo', sku: '920-004523', brand: 'Logitech', cat: 'Peripherals' },
  { name: 'Ubiquiti UniFi FlexHD Access Point', sku: 'UAP-FLEXHD', brand: 'Ubiquiti', cat: 'Networking' },
]

const brands = ['Hikvision', 'Ubiquiti', 'TP-Link', 'APC', 'Logitech', 'Mecer']
</script>

<template>
  <Head title="Products — Herocom Distribution" />
  <MarketingLayout>
    <div class="prod">
      <!-- Header -->
      <section class="prodhero">
        <div class="prodhero__inner">
          <span class="eyebrow">Catalogue</span>
          <h1 class="section-title prodhero__title">The electronics resellers actually sell.</h1>
          <p class="prodhero__lead">Surveillance, networking, power and peripherals from the brands your customers trust. Sign in for live stock and your trade pricing.</p>
          <div class="prodhero__cta">
            <Button as="a" href="/login">Sign in to view pricing</Button>
            <Button as="a" href="/apply" variant="outline">Apply for an account</Button>
          </div>
        </div>
      </section>

      <!-- Categories -->
      <section class="cats">
        <div class="cats__grid">
          <Link v-for="c in categories" :key="c.h" :href="c.href" class="catcard" :aria-label="c.aria">
            <div class="catcard__icon"><component :is="c.icon" /></div>
            <div class="catcard__body">
              <h3 class="catcard__h">{{ c.h }}</h3>
              <p class="catcard__b">{{ c.b }}</p>
            </div>
            <span class="catcard__n">{{ c.n }}</span>
          </Link>
        </div>
      </section>

      <!-- Featured -->
      <section class="featured">
        <div class="featured__head">
          <h2 class="section-title">Featured lines</h2>
          <span class="featured__note">Pricing shown to approved resellers</span>
        </div>
        <div class="featured__grid">
          <div v-for="p in featured" :key="p.sku" class="fcard">
            <div class="fcard__media"><Package /></div>
            <span class="fcard__cat">{{ p.cat }}</span>
            <h3 class="fcard__name">{{ p.name }}</h3>
            <p class="fcard__meta">{{ p.sku }} · {{ p.brand }}</p>
            <div class="fcard__price">
              <Lock /> Sign in for trade pricing
            </div>
          </div>
        </div>
      </section>

      <!-- Brands -->
      <section class="brands brands--solo">
        <span class="brands__label">Authorised distributor for</span>
        <div class="brands__row">
          <span v-for="b in brands" :key="b" class="brands__chip">{{ b }}</span>
        </div>
      </section>

      <!-- CTA band -->
      <section class="band">
        <div class="band__glow" />
        <div class="band__inner">
          <div>
            <h2 class="band__title">See the full catalogue.</h2>
            <p class="band__sub">Approved resellers get live stock, trade pricing and online ordering across every line.</p>
          </div>
          <Button as="a" href="/apply" size="lg">Apply for an account <ArrowRight /></Button>
        </div>
      </section>
    </div>
  </MarketingLayout>
</template>
