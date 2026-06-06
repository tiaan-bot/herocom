<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'

type OrderStatus = 'placed' | 'accepted' | 'rejected' | 'cancelled'

interface OrderRow {
  uuid: string
  number: string | null
  date: string | null
  status: OrderStatus
  total: number
  currency: string
}

interface Paginated<T> {
  data: T[]
  current_page: number
  last_page: number
  prev_page_url: string | null
  next_page_url: string | null
  total: number
}

defineProps<{ orders: Paginated<OrderRow> }>()

const STATUS: Record<OrderStatus, { label: string; class: string }> = {
  placed: { label: 'Placed', class: 'bg-blue-100 text-blue-800' },
  accepted: { label: 'Accepted', class: 'bg-green-100 text-green-800' },
  rejected: { label: 'Rejected', class: 'bg-red-100 text-red-700' },
  cancelled: { label: 'Cancelled', class: 'bg-gray-100 text-gray-700' },
}

function money(value: number, currency: string): string {
  return `${currency} ${value.toFixed(2)}`
}
</script>

<template>
  <Head title="My orders — Herocom Distribution" />
  <PortalLayout>
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">My orders</h1>

    <div v-if="orders.data.length" class="overflow-hidden rounded-lg border bg-background">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40 text-left text-xs uppercase text-muted-foreground">
          <tr>
            <th class="px-4 py-2 font-medium">Order</th>
            <th class="px-4 py-2 font-medium">Date</th>
            <th class="px-4 py-2 font-medium">Status</th>
            <th class="px-4 py-2 text-right font-medium">Total (ex VAT)</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="order in orders.data" :key="order.uuid" class="border-b last:border-0 hover:bg-muted/30">
            <td class="px-4 py-3">
              <Link :href="`/orders/${order.uuid}`" class="font-medium text-primary hover:underline">{{ order.number }}</Link>
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ order.date }}</td>
            <td class="px-4 py-3">
              <span :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium', STATUS[order.status].class]">{{ STATUS[order.status].label }}</span>
            </td>
            <td class="px-4 py-3 text-right tabular-nums">{{ money(order.total, order.currency) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="rounded-lg border bg-background p-12 text-center text-muted-foreground">
      No orders yet. <Link href="/catalog" class="text-primary hover:underline">Browse the catalogue</Link>.
    </div>

    <div v-if="orders.last_page > 1" class="mt-6 flex justify-end gap-2">
      <Button variant="outline" size="sm" :disabled="!orders.prev_page_url" @click="orders.prev_page_url && router.visit(orders.prev_page_url)">Previous</Button>
      <Button variant="outline" size="sm" :disabled="!orders.next_page_url" @click="orders.next_page_url && router.visit(orders.next_page_url)">Next</Button>
    </div>
  </PortalLayout>
</template>
