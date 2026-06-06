<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import { Clock } from 'lucide-vue-next'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/components/ui/button'

const props = defineProps<{ resendUrl: string }>()

const form = useForm({})

function resend(): void {
  form.post(props.resendUrl)
}
</script>

<template>
  <Head title="Link expired — Herocom Distribution" />
  <GuestLayout>
    <div class="text-center">
      <Clock class="mx-auto size-10 text-muted-foreground/50" />
      <h1 class="mt-3 text-lg font-semibold">This link has expired</h1>
      <p class="mt-1 text-sm text-muted-foreground">
        Set-password links are valid for 7 days. Request a fresh one and we'll email it to you.
      </p>
      <Button class="mt-5 w-full" :disabled="form.processing" @click="resend">Send me a new link</Button>
    </div>
  </GuestLayout>
</template>
