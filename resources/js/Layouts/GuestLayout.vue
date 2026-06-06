<script setup lang="ts">
import { computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const page = usePage()
const status = computed(() => (page.props.flash as { status?: string } | undefined)?.status)
</script>

<template>
  <div class="grid min-h-screen place-items-center bg-muted/30 px-6 py-10">
    <div class="w-full max-w-sm">
      <Link href="/" class="mb-6 flex items-center justify-center gap-2 font-semibold tracking-tight">
        <img src="/images/brand/nexus-mark.png" alt="Herocom Distribution" class="h-9 w-auto">
        Herocom Distribution
      </Link>
      <div class="rounded-xl border bg-background p-6 shadow-sm">
        <p v-if="status" class="mb-4 rounded-md bg-green-50 px-3 py-2 text-sm text-green-800">{{ status }}</p>
        <slot />
      </div>
    </div>
  </div>
</template>
