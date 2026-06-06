<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'

type InvoiceStatus = 'draft' | 'sent' | 'overdue' | 'paid' | 'partially_paid' | 'void' | 'unknown'

interface InvoiceRow {
  uuid: string
  number: string
  date: string | null
  due_date: string | null
  status: InvoiceStatus
  total: number
  balance: number
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

defineProps<{ invoices: Paginated<InvoiceRow> }>()

const STATUS: Record<InvoiceStatus, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-gray-100 text-gray-700' },
  sent: { label: 'Sent', class: 'bg-blue-100 text-blue-800' },
  overdue: { label: 'Overdue', class: 'bg-red-100 text-red-700' },
  paid: { label: 'Paid', class: 'bg-green-100 text-green-800' },
  partially_paid: { label: 'Partially paid', class: 'bg-amber-100 text-amber-800' },
  void: { label: 'Void', class: 'bg-gray-100 text-gray-500' },
  unknown: { label: 'Unknown', class: 'bg-gray-100 text-gray-700' },
}

function money(value: number, currency: string): string {
  return `${currency} ${value.toFixed(2)}`
}
</script>

<template>
  <Head title="My invoices — Herocom Distribution" />
  <PortalLayout>
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">My invoices</h1>

    <div v-if="invoices.data.length" class="overflow-hidden rounded-lg border bg-background">
      <table class="w-full text-sm">
        <thead class="border-b bg-muted/40 text-left text-xs uppercase text-muted-foreground">
          <tr>
            <th class="px-4 py-2 font-medium">Invoice</th>
            <th class="px-4 py-2 font-medium">Date</th>
            <th class="px-4 py-2 font-medium">Due</th>
            <th class="px-4 py-2 font-medium">Status</th>
            <th class="px-4 py-2 text-right font-medium">Total</th>
            <th class="px-4 py-2 text-right font-medium">Balance</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="invoice in invoices.data" :key="invoice.uuid" class="border-b last:border-0 hover:bg-muted/30">
            <td class="px-4 py-3">
              <Link :href="`/invoices/${invoice.uuid}`" class="font-medium text-primary hover:underline">{{ invoice.number }}</Link>
            </td>
            <td class="px-4 py-3 text-muted-foreground">{{ invoice.date }}</td>
            <td class="px-4 py-3 text-muted-foreground">{{ invoice.due_date }}</td>
            <td class="px-4 py-3">
              <span :class="['inline-block rounded px-1.5 py-0.5 text-xs font-medium', STATUS[invoice.status].class]">{{ STATUS[invoice.status].label }}</span>
            </td>
            <td class="px-4 py-3 text-right tabular-nums">{{ money(invoice.total, invoice.currency) }}</td>
            <td class="px-4 py-3 text-right tabular-nums" :class="invoice.balance > 0 ? 'font-medium' : 'text-muted-foreground'">{{ money(invoice.balance, invoice.currency) }}</td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-else class="rounded-lg border bg-background p-12 text-center text-muted-foreground">
      No invoices yet. Invoices appear here once Herocom raises them in Zoho Books.
    </div>

    <div v-if="invoices.last_page > 1" class="mt-6 flex justify-end gap-2">
      <Button variant="outline" size="sm" :disabled="!invoices.prev_page_url" @click="invoices.prev_page_url && router.visit(invoices.prev_page_url)">Previous</Button>
      <Button variant="outline" size="sm" :disabled="!invoices.next_page_url" @click="invoices.next_page_url && router.visit(invoices.next_page_url)">Next</Button>
    </div>
  </PortalLayout>
</template>
