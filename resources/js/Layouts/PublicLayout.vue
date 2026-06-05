<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen bg-muted/30 text-foreground">
    <header class="border-b bg-background">
      <div class="mx-auto flex max-w-4xl items-center justify-between px-6 py-4">
        <Link href="/" class="flex items-center gap-2 font-semibold tracking-tight">
          <span class="grid size-7 place-items-center rounded-md bg-primary text-primary-foreground text-sm">H</span>
          Herocom Distribution
        </Link>
        <span class="text-sm text-muted-foreground">Reseller onboarding</span>
      </div>
    </header>

    <main class="mx-auto max-w-4xl px-6 py-10">
      <slot />
    </main>

    <footer class="mx-auto max-w-4xl px-6 pb-10 text-center text-xs text-muted-foreground">
      © Herocom Distribution. Your information is processed in line with POPIA.
    </footer>
  </div>
</template>
