<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Checkbox } from '@/components/ui/checkbox'

defineProps<{ canResetPassword?: boolean }>()

const form = useForm({ email: '', password: '', remember: false })

function submit(): void {
  form.post('/login', { onFinish: () => form.reset('password') })
}
</script>

<template>
  <Head title="Sign in — Herocom Distribution" />
  <GuestLayout>
    <h1 class="mb-1 text-lg font-semibold">Sign in</h1>
    <p class="mb-4 text-sm text-muted-foreground">Welcome back to Herocom Distribution.</p>

    <form class="space-y-4" @submit.prevent="submit">
      <div class="space-y-1.5">
        <Label for="email">Email</Label>
        <Input id="email" v-model="form.email" type="email" autocomplete="email" autofocus />
        <p v-if="form.errors.email" class="text-xs font-medium text-destructive">{{ form.errors.email }}</p>
      </div>

      <div class="space-y-1.5">
        <Label for="password">Password</Label>
        <Input id="password" v-model="form.password" type="password" autocomplete="current-password" />
      </div>

      <div class="flex items-center justify-between">
        <label class="flex items-center gap-2 text-sm"><Checkbox v-model="form.remember" /> Remember me</label>
        <Link v-if="canResetPassword" href="/forgot-password" class="text-sm text-primary hover:underline">Forgot password?</Link>
      </div>

      <Button type="submit" class="w-full" :disabled="form.processing">Sign in</Button>
    </form>

    <p class="mt-6 text-center text-sm text-muted-foreground">
      Want to become a reseller?
      <Link href="/apply" class="text-primary hover:underline">Apply</Link>
    </p>
  </GuestLayout>
</template>
