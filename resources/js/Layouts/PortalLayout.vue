<script setup lang="ts">
import { computed } from 'vue'
import { Link, router, usePage } from '@inertiajs/vue3'
import { ShoppingCart } from 'lucide-vue-next'

interface AuthUser {
  name: string
  email: string
  company: string | null
  can: { place_orders: boolean }
}

const page = usePage()
const user = computed(() => (page.props.auth as { user: AuthUser | null } | undefined)?.user ?? null)
const cartCount = computed(() => (page.props.cartCount as number | undefined) ?? 0)

function logout(): void {
  router.post('/logout')
}
</script>

<template>
  <div class="min-h-screen bg-muted/30 text-foreground">
    <header class="border-b bg-background">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-3">
        <div class="flex items-center gap-6">
          <Link href="/catalog" class="flex items-center gap-2 font-semibold tracking-tight">
            <span class="grid size-7 place-items-center rounded-md bg-primary text-sm text-primary-foreground">H</span>
            Herocom Distribution
          </Link>
          <nav class="hidden items-center gap-4 text-sm sm:flex">
            <Link href="/catalog" class="text-foreground hover:text-primary">Catalogue</Link>
            <!-- Placeholders — pages arrive in later passes. -->
            <span class="cursor-not-allowed text-muted-foreground/50" title="Coming soon">Orders</span>
            <span class="cursor-not-allowed text-muted-foreground/50" title="Coming soon">Invoices</span>
          </nav>
        </div>

        <div v-if="user" class="flex items-center gap-3">
          <Link href="/cart" class="relative rounded-md p-2 hover:bg-accent" aria-label="Cart">
            <ShoppingCart class="size-5" />
            <span
              v-if="cartCount > 0"
              class="absolute -right-1 -top-1 grid min-w-4 place-items-center rounded-full bg-primary px-1 text-[10px] font-medium text-primary-foreground"
            >{{ cartCount }}</span>
          </Link>
          <div class="hidden text-right sm:block">
            <p class="text-sm font-medium leading-tight">{{ user.name }}</p>
            <p v-if="user.company" class="text-xs text-muted-foreground">{{ user.company }}</p>
          </div>
          <button
            type="button"
            class="rounded-md border px-3 py-1.5 text-sm hover:bg-accent"
            @click="logout"
          >
            Log out
          </button>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
      <slot />
    </main>
  </div>
</template>
