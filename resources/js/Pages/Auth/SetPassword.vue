<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PasswordFields from '@/components/PasswordFields.vue'
import { Button } from '@/components/ui/button'

const props = defineProps<{ name: string; submitUrl: string }>()

const form = useForm({ password: '', password_confirmation: '' })

function submit(): void {
  form.post(props.submitUrl)
}
</script>

<template>
  <Head title="Set your password — Herocom Distribution" />
  <GuestLayout>
    <h1 class="mb-1 text-lg font-semibold">Welcome, {{ name }}</h1>
    <p class="mb-4 text-sm text-muted-foreground">Set a password to access your reseller account.</p>

    <form class="space-y-4" @submit.prevent="submit">
      <PasswordFields
        v-model:password="form.password"
        v-model:confirmation="form.password_confirmation"
        :password-error="form.errors.password"
        autofocus
      />
      <Button type="submit" class="w-full" :disabled="form.processing">Set password &amp; continue</Button>
    </form>
  </GuestLayout>
</template>
