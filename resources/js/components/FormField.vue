<script setup lang="ts">
import { Label } from '@/components/ui/label'

withDefaults(defineProps<{
  label?: string
  error?: string
  required?: boolean
  hint?: string
  for?: string
}>(), {
  label: '',
  error: '',
  required: false,
  hint: '',
  for: '',
})
</script>

<template>
  <div class="space-y-1.5">
    <Label v-if="label" :for="$props.for">
      {{ label }}
      <span v-if="required" class="text-destructive">*</span>
    </Label>
    <slot />
    <p v-if="hint && !error" class="text-xs text-muted-foreground">{{ hint }}</p>
    <p v-if="error" class="text-xs font-medium text-destructive">{{ error }}</p>
  </div>
</template>
