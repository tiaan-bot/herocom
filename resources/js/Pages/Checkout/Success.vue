<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { CheckCircle2 } from 'lucide-vue-next'
import PortalLayout from '@/Layouts/PortalLayout.vue'
import { Button } from '@/components/ui/button'

defineProps<{ order: { number: string; uuid: string } }>()
</script>

<template>
  <Head title="Order placed — Herocom Distribution" />
  <PortalLayout>
    <div class="mx-auto max-w-lg py-10 text-center">
      <CheckCircle2 class="mx-auto size-12 text-primary" />
      <h1 class="mt-4 text-2xl font-semibold tracking-tight">Order placed</h1>
      <p class="mt-2 text-muted-foreground">
        Thanks — your order <span class="font-medium text-foreground">{{ order.number }}</span> has been received.
        We've emailed you a confirmation and our team will review it shortly.
      </p>
      <div class="mt-6 flex justify-center gap-3">
        <Button as-child><Link :href="`/orders/${order.uuid}`">View in My Orders</Link></Button>
        <Button as-child variant="outline"><Link href="/catalog">Continue shopping</Link></Button>
      </div>
    </div>
  </PortalLayout>
</template>
