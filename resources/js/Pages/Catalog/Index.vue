<script setup lang="ts">
import { reactive, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { Package, Search } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Label } from '@/components/ui/label'
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select'

type StockBandValue = 'in_stock' | 'low_stock' | 'out_of_stock'

interface ProductCard {
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
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  prev_page_url: string | null
  next_page_url: string | null
  total: number
  from: number | null
  to: number | null
}

interface Filters {
  q: string | null
  brand: string | null
  category: string | null
  in_stock: boolean
  sort: string | null
}

const props = defineProps<{
  products: Paginated<ProductCard>
  filters: Filters
  brands: string[]
  categories: string[]
}>()

const form = reactive({
  q: props.filters.q ?? '',
  brand: props.filters.brand || 'all',
  category: props.filters.category || 'all',
  in_stock: props.filters.in_stock,
  sort: props.filters.sort || 'name',
})

function apply(): void {
  router.get('/catalog', {
    q: form.q || undefined,
    brand: form.brand === 'all' ? undefined : form.brand,
    category: form.category === 'all' ? undefined : form.category,
    in_stock: form.in_stock ? 1 : undefined,
    sort: form.sort === 'name' ? undefined : form.sort,
  }, { preserveState: true, preserveScroll: true, replace: true })
}

// Selects + toggle apply immediately; the search box applies on submit.
watch(() => [form.brand, form.category, form.in_stock, form.sort], apply)

const STOCK: Record<StockBandValue, { label: string; class: string }> = {
  in_stock: { label: 'In stock', class: 'bg-green-100 text-green-800' },
  low_stock: { label: 'Low stock', class: 'bg-amber-100 text-amber-800' },
  out_of_stock: { label: 'Out of stock', class: 'bg-red-100 text-red-700' },
}

function money(value: number, currency: string): string {
  return `${currency} ${value.toFixed(2)}`
}
</script>

<template>
  <Head title="Catalogue — Herocom Distribution" />
  <PortalLayout>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">Catalogue</h1>
        <p class="text-sm text-muted-foreground">{{ products.total }} products · prices shown ex VAT</p>
      </div>

      <form class="flex items-center gap-2" @submit.prevent="apply">
        <Input v-model="form.q" type="search" placeholder="Search name, SKU, brand…" class="w-64" />
        <Button type="submit" variant="secondary"><Search class="size-4" /></Button>
      </form>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap items-center gap-3">
      <Select v-model="form.brand">
        <SelectTrigger class="w-44"><SelectValue placeholder="Brand" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All brands</SelectItem>
          <SelectItem v-for="b in brands" :key="b" :value="b">{{ b }}</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="form.category">
        <SelectTrigger class="w-44"><SelectValue placeholder="Category" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="all">All categories</SelectItem>
          <SelectItem v-for="c in categories" :key="c" :value="c">{{ c }}</SelectItem>
        </SelectContent>
      </Select>

      <Select v-model="form.sort">
        <SelectTrigger class="w-44"><SelectValue placeholder="Sort" /></SelectTrigger>
        <SelectContent>
          <SelectItem value="name">Name (A–Z)</SelectItem>
          <SelectItem value="price">Price (low–high)</SelectItem>
          <SelectItem value="price_desc">Price (high–low)</SelectItem>
        </SelectContent>
      </Select>

      <div class="flex items-center gap-2">
        <Checkbox id="in_stock" v-model="form.in_stock" />
        <Label for="in_stock" class="font-normal">In stock only</Label>
      </div>
    </div>

    <!-- Grid -->
    <div v-if="products.data.length" class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      <Link
        v-for="product in products.data" :key="product.uuid"
        :href="`/catalog/${product.uuid}`"
        class="group rounded-lg border bg-background p-3 transition-shadow hover:shadow-md"
      >
        <div class="mb-3 grid aspect-square place-items-center overflow-hidden rounded-md bg-muted">
          <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
          <Package v-else class="size-10 text-muted-foreground/40" />
        </div>
        <span :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium', STOCK[product.stock_band].class]">
          {{ STOCK[product.stock_band].label }}
        </span>
        <h2 class="mt-2 line-clamp-2 text-sm font-medium group-hover:text-primary">{{ product.name }}</h2>
        <p class="text-xs text-muted-foreground">{{ product.sku }}<span v-if="product.brand"> · {{ product.brand }}</span></p>
        <div class="mt-2">
          <p class="text-xs text-muted-foreground line-through">{{ money(product.list_price, product.currency) }}</p>
          <p class="font-semibold">{{ money(product.your_price, product.currency) }} <span class="text-xs font-normal text-muted-foreground">your price</span></p>
        </div>
      </Link>
    </div>
    <div v-else class="rounded-lg border bg-background p-12 text-center text-muted-foreground">
      No products match your filters.
    </div>

    <!-- Pagination -->
    <div v-if="products.last_page > 1" class="mt-8 flex items-center justify-between text-sm">
      <span class="text-muted-foreground">Showing {{ products.from }}–{{ products.to }} of {{ products.total }}</span>
      <div class="flex gap-2">
        <Button variant="outline" size="sm" :disabled="!products.prev_page_url" @click="products.prev_page_url && router.visit(products.prev_page_url)">Previous</Button>
        <Button variant="outline" size="sm" :disabled="!products.next_page_url" @click="products.next_page_url && router.visit(products.next_page_url)">Next</Button>
      </div>
    </div>
  </PortalLayout>
</template>
