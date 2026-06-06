<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import FormField from '@/components/FormField.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'

interface CheckoutLine {
  name: string
  sku: string | null
  quantity: number
  unit_price: number
  line_total: number
}

interface Address {
  delivery_address_line1: string | null
  delivery_address_line2: string | null
  delivery_city: string | null
  delivery_province: string | null
  delivery_postal_code: string | null
  delivery_country_code: string | null
}

const props = defineProps<{
  lines: CheckoutLine[]
  subtotal: number
  currency: string
  defaultAddress: Address
}>()

const form = useForm({
  delivery_address_line1: props.defaultAddress.delivery_address_line1 ?? '',
  delivery_address_line2: props.defaultAddress.delivery_address_line2 ?? '',
  delivery_city: props.defaultAddress.delivery_city ?? '',
  delivery_province: props.defaultAddress.delivery_province ?? '',
  delivery_postal_code: props.defaultAddress.delivery_postal_code ?? '',
  delivery_country_code: props.defaultAddress.delivery_country_code ?? 'ZA',
  customer_note: '',
})

function money(value: number): string {
  return `${props.currency} ${value.toFixed(2)}`
}

function placeOrder(): void {
  form.post('/checkout')
}
</script>

<template>
  <Head title="Checkout — Herocom Distribution" />
  <PortalLayout>
    <h1 class="mb-6 text-2xl font-semibold tracking-tight">Checkout</h1>

    <form class="grid gap-8 lg:grid-cols-3" @submit.prevent="placeOrder">
      <div class="space-y-6 lg:col-span-2">
        <section class="rounded-lg border bg-background p-5">
          <h2 class="mb-3 font-medium">Delivery address</h2>
          <div class="grid gap-4 sm:grid-cols-2">
            <FormField label="Address line 1" :error="form.errors.delivery_address_line1" required class="sm:col-span-2">
              <Input v-model="form.delivery_address_line1" />
            </FormField>
            <FormField label="Address line 2" :error="form.errors.delivery_address_line2" class="sm:col-span-2">
              <Input v-model="form.delivery_address_line2" />
            </FormField>
            <FormField label="City" :error="form.errors.delivery_city" required><Input v-model="form.delivery_city" /></FormField>
            <FormField label="Province" :error="form.errors.delivery_province" required><Input v-model="form.delivery_province" /></FormField>
            <FormField label="Postal code" :error="form.errors.delivery_postal_code" required><Input v-model="form.delivery_postal_code" /></FormField>
          </div>
        </section>

        <section class="rounded-lg border bg-background p-5">
          <h2 class="mb-3 font-medium">Order note (optional)</h2>
          <Textarea v-model="form.customer_note" placeholder="Anything we should know about this order?" />
        </section>

        <p class="text-xs text-muted-foreground">
          Free delivery in Johannesburg / Pretoria on orders ≥ R2,500 ex VAT; otherwise delivery is arranged and quoted separately.
        </p>
      </div>

      <aside class="h-fit rounded-lg border bg-background p-5">
        <h2 class="mb-3 font-medium">Your order</h2>
        <ul class="space-y-2 text-sm">
          <li v-for="line in lines" :key="line.sku ?? line.name" class="flex justify-between gap-2">
            <span class="min-w-0 truncate text-muted-foreground">{{ line.quantity }} × {{ line.name }}</span>
            <span class="tabular-nums">{{ money(line.line_total) }}</span>
          </li>
        </ul>
        <div class="mt-3 flex justify-between border-t pt-3 font-semibold">
          <span>Subtotal (ex VAT)</span>
          <span class="tabular-nums">{{ money(subtotal) }}</span>
        </div>

        <p class="mt-4 text-xs text-muted-foreground">
          By placing this order you make a binding offer per the
          <a href="/terms-of-sale.pdf" target="_blank" class="text-primary underline">Standard Terms of Sale</a>.
        </p>

        <Button type="submit" class="mt-4 w-full" :disabled="form.processing">Place order</Button>
        <Link href="/cart" class="mt-3 block text-center text-sm text-muted-foreground hover:text-foreground">Back to cart</Link>
      </aside>
    </form>
  </PortalLayout>
</template>
