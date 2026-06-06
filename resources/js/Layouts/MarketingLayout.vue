<script setup lang="ts">
import { computed, ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { Menu, X } from 'lucide-vue-next'
import { Button } from '@/components/ui/button'

interface AuthUser {
  name: string
  email: string
  company: string | null
}

const page = usePage()
const user = computed(() => (page.props.auth as { user: AuthUser | null } | undefined)?.user ?? null)

// Auth-aware nav (spec §4): authenticated resellers (have a company) go to the
// portal catalogue; internal staff (no company) go to the admin panel.
const portalHref = computed(() => (user.value?.company ? '/catalog' : '/admin'))

const navLinks = [
  { label: 'Home', href: '/' },
  { label: 'Products', href: '/products' },
  { label: 'Contact', href: '/contact' },
]

const currentPath = computed(() => {
  const url = page.url ?? '/'
  const path = url.split('?')[0]
  return path.length > 1 ? path.replace(/\/$/, '') : '/'
})

function isActive(href: string): boolean {
  return currentPath.value === href
}

const mobileOpen = ref(false)
</script>

<template>
  <div class="mkt-root">
    <header class="mnav">
      <div class="mnav__bar">
        <Link href="/" class="mnav__logo" aria-label="Herocom Distribution — home">
          <img src="/images/brand/wordmark-full-color.png" alt="Herocom Distribution" width="640" height="105">
        </Link>

        <nav class="mnav__links">
          <Link
            v-for="link in navLinks"
            :key="link.href"
            :href="link.href"
            :class="{ 'is-active': isActive(link.href) }"
          >{{ link.label }}</Link>
        </nav>

        <div class="mnav__actions">
          <template v-if="user">
            <Button as="a" :href="portalHref" size="sm">Go to portal</Button>
          </template>
          <template v-else>
            <Button as="a" href="/login" variant="ghost" size="sm">Sign in</Button>
            <Button as="a" href="/apply" size="sm">Apply</Button>
          </template>
        </div>

        <button
          type="button"
          class="mnav__toggle"
          :aria-expanded="mobileOpen"
          aria-label="Toggle menu"
          @click="mobileOpen = !mobileOpen"
        >
          <component :is="mobileOpen ? X : Menu" class="size-5" />
        </button>
      </div>

      <div v-show="mobileOpen" class="mnav__mobile">
        <div class="mnav__mobile-inner">
          <Link
            v-for="link in navLinks"
            :key="link.href"
            :href="link.href"
            :class="{ 'is-active': isActive(link.href) }"
            @click="mobileOpen = false"
          >{{ link.label }}</Link>
          <div class="mnav__mobile-actions">
            <template v-if="user">
              <Button as="a" :href="portalHref" size="sm">Go to portal</Button>
            </template>
            <template v-else>
              <Button as="a" href="/login" variant="ghost" size="sm">Sign in</Button>
              <Button as="a" href="/apply" size="sm">Apply</Button>
            </template>
          </div>
        </div>
      </div>
    </header>

    <main>
      <slot />
    </main>

    <footer class="mfoot">
      <div class="mfoot__inner">
        <div class="mfoot__brand">
          <img src="/images/brand/wordmark-white.png" alt="Herocom Distribution" width="670" height="135">
          <p>The Distribution Nexus. B2B electronics distribution for South African resellers.</p>
        </div>
        <div class="mfoot__cols">
          <div class="mfoot__col">
            <h4>Distribution</h4>
            <Link href="/products">Products</Link>
            <Link href="/products">Brands</Link>
            <Link href="/products">Trade pricing</Link>
          </div>
          <div class="mfoot__col">
            <h4>Resellers</h4>
            <a href="/apply">Apply</a>
            <a href="/login">Sign in</a>
            <a href="/apply">Credit &amp; COD</a>
          </div>
          <div class="mfoot__col">
            <h4>Company</h4>
            <Link href="/">The Nexus</Link>
            <Link href="/contact">Contact</Link>
            <Link href="/contact">POPIA</Link>
          </div>
        </div>
      </div>
      <div class="mfoot__base">
        <span>© 2026 Herocom Distribution (Pty) Ltd</span>
        <span>Your information is processed in line with POPIA.</span>
      </div>
    </footer>
  </div>
</template>
