<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { CheckCircle2 } from 'lucide-vue-next'
import PublicLayout from '@/Layouts/PublicLayout.vue'
import { Button } from '@/components/ui/button'
</script>

<template>
  <Head title="Application received — Herocom Distribution" />
  <PublicLayout>
    <div class="mx-auto max-w-lg py-10 text-center">
      <CheckCircle2 class="mx-auto size-12 text-primary" />
      <h1 class="mt-4 text-2xl font-semibold tracking-tight">Application received</h1>
      <p class="mt-2 text-muted-foreground">
        Thanks — we've got your reseller application and sent a confirmation to your email.
        Our team will review it and be in touch. If we need anything further, we'll reach out.
      </p>
      <Button as-child class="mt-6">
        <Link href="/">Back to home</Link>
      </Button>
    </div>
  </PublicLayout>
</template>
