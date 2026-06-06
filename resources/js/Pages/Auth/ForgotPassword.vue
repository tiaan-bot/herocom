<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const form = useForm({ email: '' })

function submit(): void {
  form.post('/forgot-password')
}
</script>

<template>
  <Head title="Forgot password — Herocom Distribution" />
  <GuestLayout>
    <h1 class="mb-1 text-lg font-semibold">Forgot password</h1>
    <p class="mb-4 text-sm text-muted-foreground">We'll email you a link to reset it.</p>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="form.email" type="email" autocomplete="email" autofocus />
        <p v-if="form.errors.email" class="text-xs font-medium text-destructive">{{ form.errors.email }}</p>
      </div>
      <Button type="submit" class="w-full" :disabled="form.processing">Email reset link</Button>
    </form>

    <p class="mt-6 text-center text-sm text-muted-foreground">
      <Link href="/login" class="text-primary hover:underline">Back to sign in</Link>
    </p>
  </GuestLayout>
</template>
