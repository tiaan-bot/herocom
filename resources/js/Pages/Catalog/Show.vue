<script setup lang="ts">
import { computed, ref } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ArrowLeft, Package, Plus } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'

type StockBandValue = 'in_stock' | 'low_stock' | 'out_of_stock'

interface ProductDetail {
  uuid: string
  name: string
  sku: string | null
  brand: string | null
  category: string | null
  unit: string | null
  image_url: string | null
  currency: string
  list_price: number
  your_price: number
  stock_band: StockBandValue
  description: string | null
}

const props = defineProps<{ product: ProductDetail }>()

const STOCK: Record<StockBandValue, { label: string; class: string }> = {
  in_stock: { label: 'In stock', class: 'bg-green-100 text-green-800' },
  low_stock: { label: 'Low stock', class: 'bg-amber-100 text-amber-800' },
  out_of_stock: { label: 'Out of stock', class: 'bg-red-100 text-red-700' },
}

function money(value: number, currency: string): string {
  return `${currency} ${value.toFixed(2)}`
}

const page = usePage()
const canPlaceOrders = computed(() => {
  const auth = page.props.auth as { user: { can?: { place_orders?: boolean } } | null } | undefined
  return auth?.user?.can?.place_orders ?? false
})

const quantity = ref(1)

function addToCart(): void {
  router.post('/cart', { product: props.product.uuid, quantity: quantity.value }, { preserveScroll: true })
}
</script>

<template>
  <Head :title="`${product.name} — Herocom Distribution`" />
  <PortalLayout>
    <Link href="/catalog" class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="size-4" /> Back to catalogue
    </Link>

    <div class="grid gap-8 md:grid-cols-2">
      <div class="grid aspect-square place-items-center overflow-hidden rounded-lg border bg-muted">
        <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
        <Package v-else class="size-20 text-muted-foreground/30" />
      </div>

      <div>
        <span :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium', STOCK[product.stock_band].class]">
          {{ STOCK[product.stock_band].label }}
        </span>
        <h1 class="mt-2 text-2xl font-semibold tracking-tight">{{ product.name }}</h1>
        <p class="mt-1 text-sm text-muted-foreground">
          <span v-if="product.sku">SKU {{ product.sku }}</span>
          <span v-if="product.brand"> · {{ product.brand }}</span>
          <span v-if="product.unit"> · per {{ product.unit }}</span>
        </p>

        <div class="mt-5 rounded-lg border bg-background p-4">
          <p v-if="product.list_price > product.your_price" class="text-sm text-muted-foreground line-through">List {{ money(product.list_price, product.currency) }}</p>
          <p class="text-2xl font-semibold">{{ money(product.your_price, product.currency) }}</p>
          <p class="text-xs text-muted-foreground">your price · ex VAT</p>

          <div v-if="canPlaceOrders" class="mt-4 flex items-center gap-2">
            <Input v-model.number="quantity" type="number" min="1" class="w-20" />
            <Button type="button" @click="addToCart"><Plus class="size-4" /> Add to cart</Button>
          </div>
        </div>

        <div v-if="product.description" class="mt-6">
          <h2 class="mb-1 text-sm font-medium">Description</h2>
          <p class="whitespace-pre-line text-sm text-muted-foreground">{{ product.description }}</p>
        </div>
      </div>
    </div>
  </PortalLayout>
</template>
