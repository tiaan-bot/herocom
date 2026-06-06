<script setup lang="ts">
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

defineProps<{
  passwordError?: string
  autofocus?: boolean
}>()

const password = defineModel<string>('password', { required: true })
const confirmation = defineModel<string>('confirmation', { required: true })
</script>

<template>
  <div class="space-y-4">
    <div class="space-y-1.5">
      <Label for="password">Password</Label>
      <Input id="password" v-model="password" type="password" autocomplete="new-password" :autofocus="autofocus" />
      <p v-if="passwordError" class="text-xs font-medium text-destructive">{{ passwordError }}</p>
      <p v-else class="text-xs text-muted-foreground">At least 8 characters.</p>
    </div>
    <div class="space-y-1.5">
      <Label for="password_confirmation">Confirm password</Label>
      <Input id="password_confirmation" v-model="confirmation" type="password" autocomplete="new-password" />
    </div>
  </div>
</template>
