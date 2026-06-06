<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import PasswordFields from '@/components/PasswordFields.vue'
import { Button } from '@/components/ui/button'

const props = defineProps<{ token: string; email: string }>()

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
})

function submit(): void {
  form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') })
}
</script>

<template>
  <Head title="Reset password — Herocom Distribution" />
  <GuestLayout>
    <h1 class="mb-1 text-lg font-semibold">Reset password</h1>
    <p class="mb-4 text-sm text-muted-foreground">Choose a new password for {{ form.email }}.</p>

    <form class="space-y-4" @submit.prevent="submit">
      <PasswordFields
        v-model:password="form.password"
        v-model:confirmation="form.password_confirmation"
        :password-error="form.errors.password"
        autofocus
      />
      <p v-if="form.errors.email" class="text-xs font-medium text-destructive">{{ form.errors.email }}</p>
      <Button type="submit" class="w-full" :disabled="form.processing">Reset password</Button>
    </form>
  </GuestLayout>
</template>
