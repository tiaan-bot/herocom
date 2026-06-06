<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'

type OrderStatus = 'placed' | 'accepted' | 'rejected' | 'cancelled'

interface OrderLine {
  name: string
  sku: string | null
  quantity: number
  unit_price: number
  line_total: number
}

interface OrderDetail {
  number: string | null
  status: OrderStatus
  date: string | null
  currency: string
  subtotal: number
  note: string | null
  delivery: {
    line1: string
    line2: string | null
    city: string
    province: string
    postal_code: string
  }
  lines: OrderLine[]
}

const props = defineProps<{ order: OrderDetail }>()

const STATUS: Record<OrderStatus, { label: string; class: string }> = {
  placed: { label: 'Placed', class: 'bg-blue-100 text-blue-800' },
  accepted: { label: 'Accepted', class: 'bg-green-100 text-green-800' },
  rejected: { label: 'Rejected', class: 'bg-red-100 text-red-700' },
  cancelled: { label: 'Cancelled', class: 'bg-gray-100 text-gray-700' },
}

function money(value: number): string {
  return `${props.order.currency} ${value.toFixed(2)}`
}
</script>

<template>
  <Head :title="`Order ${order.number} — Herocom Distribution`" />
  <PortalLayout>
    <Link href="/orders" class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="size-4" /> Back to orders
    </Link>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ order.number }}</h1>
        <p class="text-sm text-muted-foreground">Placed {{ order.date }}</p>
      </div>
      <span :class="['inline-block rounded px-2 py-1 text-xs font-medium', STATUS[order.status].class]">{{ STATUS[order.status].label }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="overflow-hidden rounded-lg border bg-background lg:col-span-2">
        <table class="w-full text-sm">
          <thead class="border-b bg-muted/40 text-left text-xs uppercase text-muted-foreground">
            <tr>
              <th class="px-4 py-2 font-medium">Item</th>
              <th class="px-4 py-2 text-right font-medium">Qty</th>
              <th class="px-4 py-2 text-right font-medium">Unit</th>
              <th class="px-4 py-2 text-right font-medium">Total</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(line, i) in order.lines" :key="i" class="border-b last:border-0">
              <td class="px-4 py-3">
                <div class="font-medium">{{ line.name }}</div>
                <div class="text-xs text-muted-foreground">{{ line.sku }}</div>
              </td>
              <td class="px-4 py-3 text-right tabular-nums">{{ line.quantity }}</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(line.unit_price) }}</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(line.line_total) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="border-t font-semibold">
              <td class="px-4 py-3" colspan="3">Subtotal (ex VAT)</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(order.subtotal) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <aside class="h-fit space-y-4">
        <div class="rounded-lg border bg-background p-4">
          <h2 class="mb-2 text-sm font-medium">Delivery address</h2>
          <p class="text-sm text-muted-foreground">
            {{ order.delivery.line1 }}<br>
            <template v-if="order.delivery.line2">{{ order.delivery.line2 }}<br></template>
            {{ order.delivery.city }}, {{ order.delivery.province }} {{ order.delivery.postal_code }}
          </p>
        </div>
        <div v-if="order.note" class="rounded-lg border bg-background p-4">
          <h2 class="mb-2 text-sm font-medium">Order note</h2>
          <p class="text-sm text-muted-foreground">{{ order.note }}</p>
        </div>
      </aside>
    </div>
  </PortalLayout>
</template>
