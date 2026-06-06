<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { ArrowLeft, ExternalLink } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'

type InvoiceStatus = 'draft' | 'sent' | 'overdue' | 'paid' | 'partially_paid' | 'void' | 'unknown'

interface InvoiceDetail {
  number: string
  status: InvoiceStatus
  date: string | null
  due_date: string | null
  currency: string
  subtotal_ex_vat: number
  tax_total: number
  total: number
  balance: number
  order_number: string | null
  payment_url: string | null
  can_pay: boolean
}

const props = defineProps<{ invoice: InvoiceDetail }>()

const STATUS: Record<InvoiceStatus, { label: string; class: string }> = {
  draft: { label: 'Draft', class: 'bg-gray-100 text-gray-700' },
  sent: { label: 'Sent', class: 'bg-blue-100 text-blue-800' },
  overdue: { label: 'Overdue', class: 'bg-red-100 text-red-700' },
  paid: { label: 'Paid', class: 'bg-green-100 text-green-800' },
  partially_paid: { label: 'Partially paid', class: 'bg-amber-100 text-amber-800' },
  void: { label: 'Void', class: 'bg-gray-100 text-gray-500' },
  unknown: { label: 'Unknown', class: 'bg-gray-100 text-gray-700' },
}

function money(value: number): string {
  return `${props.invoice.currency} ${value.toFixed(2)}`
}
</script>

<template>
  <Head :title="`Invoice ${invoice.number} — Herocom Distribution`" />
  <PortalLayout>
    <Link href="/invoices" class="mb-6 inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
      <ArrowLeft class="size-4" /> Back to invoices
    </Link>

    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h1 class="text-2xl font-semibold tracking-tight">{{ invoice.number }}</h1>
        <p class="text-sm text-muted-foreground">Issued {{ invoice.date }}</p>
      </div>
      <span :class="['inline-block rounded px-2 py-1 text-xs font-medium', STATUS[invoice.status].class]">{{ STATUS[invoice.status].label }}</span>
    </div>

    <div
      v-if="invoice.status === 'overdue'"
      class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"
    >
      This invoice is overdue. Please settle the outstanding balance of {{ money(invoice.balance) }}.
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
      <div class="overflow-hidden rounded-lg border bg-background lg:col-span-2">
        <table class="w-full text-sm">
          <tbody>
            <tr class="border-b">
              <td class="px-4 py-3 text-muted-foreground">Subtotal (ex VAT)</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(invoice.subtotal_ex_vat) }}</td>
            </tr>
            <tr class="border-b">
              <td class="px-4 py-3 text-muted-foreground">VAT</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(invoice.tax_total) }}</td>
            </tr>
            <tr class="border-b font-semibold">
              <td class="px-4 py-3">Total</td>
              <td class="px-4 py-3 text-right tabular-nums">{{ money(invoice.total) }}</td>
            </tr>
            <tr class="font-semibold">
              <td class="px-4 py-3">Balance due</td>
              <td class="px-4 py-3 text-right tabular-nums" :class="invoice.balance > 0 ? 'text-red-700' : 'text-green-700'">{{ money(invoice.balance) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <aside class="h-fit space-y-4">
        <div v-if="invoice.can_pay && invoice.payment_url" class="rounded-lg border bg-background p-4">
          <h2 class="mb-1 text-sm font-medium">Outstanding balance</h2>
          <p class="mb-3 text-2xl font-semibold tabular-nums">{{ money(invoice.balance) }}</p>
          <Button as-child class="w-full">
            <a :href="invoice.payment_url" target="_blank" rel="noopener" class="inline-flex items-center justify-center gap-2">
              Pay now <ExternalLink class="size-4" />
            </a>
          </Button>
          <p class="mt-2 text-xs text-muted-foreground">Opens Zoho's secure payment page.</p>
        </div>

        <div class="rounded-lg border bg-background p-4 text-sm">
          <dl class="space-y-2">
            <div class="flex justify-between">
              <dt class="text-muted-foreground">Invoice date</dt>
              <dd>{{ invoice.date ?? '—' }}</dd>
            </div>
            <div class="flex justify-between">
              <dt class="text-muted-foreground">Due date</dt>
              <dd>{{ invoice.due_date ?? '—' }}</dd>
            </div>
            <div v-if="invoice.order_number" class="flex justify-between">
              <dt class="text-muted-foreground">Order</dt>
              <dd class="font-medium">{{ invoice.order_number }}</dd>
            </div>
          </dl>
        </div>
      </aside>
    </div>
  </PortalLayout>
</template>
