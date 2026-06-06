<script setup lang="ts">
import { Link } from '@inertiajs/vue3'
</script>

<template>
  <div class="min-h-screen bg-muted/30 text-foreground">
    <header class="border-b bg-background">
      <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
        <Link href="/catalog" class="flex items-center gap-2 font-semibold tracking-tight">
          <span class="grid size-7 place-items-center rounded-md bg-primary text-sm text-primary-foreground">H</span>
          Herocom Distribution
        </Link>
        <span class="text-sm text-muted-foreground">Catalogue</span>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6 py-8">
      <slot />
    </main>
  </div>
</template>
