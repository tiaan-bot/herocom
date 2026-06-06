<script setup lang="ts">
import type { Component } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import {
  ArrowRight,
  Boxes,
  Cpu,
  MonitorSmartphone,
  ShieldCheck,
  Tag,
  TrendingUp,
  Waypoints,
} from 'lucide-vue-next'
import MarketingLayout from '@/Layouts/MarketingLayout.vue'
import { Button } from '@/components/ui/button'

const brands = ['Hikvision', 'Ubiquiti', 'TP-Link', 'APC', 'Logitech', 'Mecer']

const stats: [string, string][] = [
  ['6+', 'leading brands stocked'],
  ['Credit & COD', 'accounts'],
  ['48hr', 'national dispatch'],
  ['100%', 'online trade ordering'],
]

interface Pillar { icon: Component, n: string, h: string, b: string }
const pillars: Pillar[] = [
  { icon: Waypoints, n: '01', h: 'Synchronicity', b: 'We align complex variables — stock, pricing, logistics — into a single, seamless flow.' },
  { icon: Cpu, n: '02', h: 'Intelligence', b: 'Our approach is rooted in data-driven precision and architectural stability.' },
  { icon: TrendingUp, n: '03', h: 'Expansion', b: 'Infrastructure that lets our partners scale their reach without losing their focus.' },
]

interface Benefit { icon: Component, h: string, b: string }
const benefits: Benefit[] = [
  { icon: Tag, h: 'Trade pricing', b: 'Tiered reseller pricing on every line, shown ex VAT the moment you sign in.' },
  { icon: Boxes, h: 'Live stock', b: 'Real-time availability across the catalogue — no phone calls to check what is in.' },
  { icon: MonitorSmartphone, h: 'Online ordering', b: 'Place and track orders from any device. Your cart, your history, always in sync.' },
  { icon: ShieldCheck, h: 'Credit & COD', b: 'Apply once for a credit facility, or trade on COD upfront — whichever suits your business.' },
]
</script>

<template>
  <Head title="Herocom Distribution — The Distribution Nexus" />
  <MarketingLayout>
    <div class="mkt">
      <!-- Hero -->
      <section class="hero">
        <div class="hero__glow" />
        <div class="hero__inner">
          <div class="hero__copy">
            <span class="hero__eyebrow">The Distribution Nexus</span>
            <h1 class="hero__title display-aspira">Your Top ICT Distributor</h1>
            <p class="hero__lead">Herocom is the B2B electronics distributor where SA resellers get trade pricing, live stock and online ordering.</p>
            <div class="hero__cta">
              <Button as="a" href="/apply" size="lg">Apply for an account <ArrowRight /></Button>
              <Button :as="Link" href="/products" size="lg" variant="outline">Browse products</Button>
            </div>
            <p class="hero__note">Credit &amp; COD accounts · approved in days, not weeks.</p>
          </div>
          <div class="hero__mark">
            <img src="/images/brand/nexus-mark.png" alt="The Herocom Nexus mark" width="1247" height="999">
          </div>
        </div>
      </section>

      <!-- Brands strip -->
      <section class="brands">
        <span class="brands__label">Distributing the brands resellers ask for</span>
        <div class="brands__row">
          <span v-for="b in brands" :key="b" class="brands__chip">{{ b }}</span>
        </div>
      </section>

      <!-- Stats -->
      <section class="stats">
        <div v-for="[n, l] in stats" :key="l" class="stats__item">
          <div class="stats__n">{{ n }}</div>
          <div class="stats__l">{{ l }}</div>
        </div>
      </section>

      <!-- Pillars -->
      <section class="pillars">
        <div class="pillars__head">
          <span class="eyebrow">Our DNA</span>
          <h2 class="section-title">We are the intelligence that holds the chain together.</h2>
        </div>
        <div class="pillars__grid">
          <div v-for="p in pillars" :key="p.h" class="pillar">
            <div class="pillar__icon"><component :is="p.icon" /></div>
            <span class="pillar__n">{{ p.n }}</span>
            <h3 class="pillar__h">{{ p.h }}</h3>
            <p class="pillar__b">{{ p.b }}</p>
          </div>
        </div>
      </section>

      <!-- Benefits -->
      <section class="benefits">
        <div class="benefits__inner">
          <div class="benefits__head">
            <span class="eyebrow">For resellers</span>
            <h2 class="section-title">Everything you need to sell more, faster.</h2>
            <p class="benefits__sub">One approved account unlocks the catalogue, your pricing and the tools to order at trade.</p>
          </div>
          <div class="benefits__grid">
            <div v-for="bn in benefits" :key="bn.h" class="benefit">
              <div class="benefit__icon"><component :is="bn.icon" /></div>
              <div>
                <h3 class="benefit__h">{{ bn.h }}</h3>
                <p class="benefit__b">{{ bn.b }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Dark CTA band -->
      <section class="band">
        <div class="band__glow" />
        <div class="band__inner">
          <div>
            <h2 class="band__title">Become a Herocom reseller.</h2>
            <p class="band__sub">Apply once. Get trade pricing, a live catalogue and online ordering — with flexible credit terms.</p>
          </div>
          <Button as="a" href="/apply" size="lg">Start your application <ArrowRight /></Button>
        </div>
      </section>
    </div>
  </MarketingLayout>
</template>
