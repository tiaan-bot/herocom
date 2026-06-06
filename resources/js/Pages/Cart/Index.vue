<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import { Minus, Package, Plus, Trash2 } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'

interface CartLine {
  id: number
  product_uuid: string
  name: string
  sku: string | null
  image_url: string | null
  quantity: number
  list_price: number
  your_price: number
  line_total: number
  currency: string
  available: boolean
}

const props = defineProps<{
  lines: CartLine[]
  subtotal: number
  currency: string
  hasUnavailable: boolean
}>()

function money(value: number): string {
  return `${props.currency} ${value.toFixed(2)}`
}

function setQuantity(line: CartLine, quantity: number): void {
  router.patch(`/cart/items/${line.id}`, { quantity }, { preserveScroll: true })
}

function remove(line: CartLine): void {
  router.delete(`/cart/items/${line.id}`, { preserveScroll: true })
}
</script>

<template>
  <Head title="Cart — Herocom Distribution" />
  <PortalLayout>
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Your cart</h1>

    <div v-if="lines.length" class="grid gap-8 lg:grid-cols-3">
      <div class="space-y-3 lg:col-span-2">
        <div
          v-for="line in lines" :key="line.id"
          class="flex items-center gap-4 rounded-lg border bg-background p-3"
          :class="{ 'opacity-60': !line.available }"
        >
          <div class="grid size-16 shrink-0 place-items-center overflow-hidden rounded-md bg-muted">
            <img v-if="line.image_url" :src="line.image_url" :alt="line.name" class="h-full w-full object-cover" />
            <Package v-else class="size-6 text-muted-foreground/40" />
          </div>

          <div class="min-w-0 flex-1">
            <Link :href="`/catalog/${line.product_uuid}`" class="font-medium hover:text-primary">{{ line.name }}</Link>
            <p class="text-xs text-muted-foreground">{{ line.sku }}</p>
            <p v-if="!line.available" class="mt-0.5 text-xs font-medium text-destructive">No longer available — remove to check out</p>
            <p v-else class="mt-0.5 text-sm">{{ money(line.your_price) }} <span class="text-xs text-muted-foreground">each, ex VAT</span></p>
          </div>

          <div class="flex items-center gap-1">
            <Button type="button" variant="outline" size="icon-sm" :disabled="!line.available" @click="setQuantity(line, line.quantity - 1)"><Minus class="size-3" /></Button>
            <span class="w-10 text-center text-sm tabular-nums">{{ line.quantity }}</span>
            <Button type="button" variant="outline" size="icon-sm" :disabled="!line.available" @click="setQuantity(line, line.quantity + 1)"><Plus class="size-3" /></Button>
          </div>

          <div class="w-24 text-right">
            <p v-if="line.available" class="font-semibold tabular-nums">{{ money(line.line_total) }}</p>
            <Button type="button" variant="ghost" size="icon-sm" class="mt-1" @click="remove(line)"><Trash2 class="size-4" /></Button>
          </div>
        </div>
      </div>

      <aside class="h-fit rounded-lg border bg-background p-5">
        <h2 class="mb-3 font-medium">Summary</h2>
        <div class="flex items-center justify-between text-sm">
          <span class="text-muted-foreground">Subtotal (ex VAT)</span>
          <span class="font-semibold tabular-nums">{{ money(subtotal) }}</span>
        </div>
        <p v-if="hasUnavailable" class="mt-2 text-xs text-destructive">Unavailable items are excluded from the total.</p>
        <Button as-child class="mt-4 w-full">
          <Link href="/checkout">Proceed to checkout</Link>
        </Button>
        <Link href="/catalog" class="mt-3 block text-center text-sm text-muted-foreground hover:text-foreground">Continue shopping</Link>
      </aside>
    </div>

    <div v-else class="rounded-lg border bg-background p-12 text-center text-muted-foreground">
      Your cart is empty. <Link href="/catalog" class="text-primary hover:underline">Browse the catalogue</Link>.
    </div>
  </PortalLayout>
</template>
