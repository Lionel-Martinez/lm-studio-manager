
<script setup lang="ts">
import {
  Search,
  Sun,
  Moon,
  Bell,
  Command,
} from 'lucide-vue-next'

import { useTheme } from '@/composables/useTheme'

defineProps<{
  title: string
  description?: string
}>()

const { theme, toggleTheme } = useTheme()

const today = new Intl.DateTimeFormat('es-AR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
}).format(new Date())
</script>

<template>
  <header class="flex min-h-20 items-center justify-between gap-4 border-b border-app-border bg-app-surface px-6">
    <div class="min-w-0">
      <h1 class="text-xl font-bold tracking-tight text-app-text">
        {{ title }}
      </h1>

      <p v-if="description" class="mt-1 text-sm text-app-muted">
        {{ description }}
      </p>
    </div>

    <div class="flex shrink-0 items-center gap-3">
      <span class="hidden text-xs font-medium capitalize text-app-muted xl:block">
        {{ today }}
      </span>

      <div class="hidden h-6 w-px bg-app-border xl:block"></div>

      <button
        type="button"
        aria-label="Buscar"
        class="hidden items-center gap-2 rounded-lg border border-app-border bg-app-elevated px-3 py-2 text-sm text-app-muted transition hover:border-brand-500/50 md:flex"
      >
        <Search :size="16" />
        <span>Buscar...</span>
        <kbd class="ml-5 flex items-center gap-0.5 rounded border border-app-border px-1.5 py-0.5 text-[10px]">
          <Command :size="10" /> K
        </kbd>
      </button>

      <button
        type="button"
        aria-label="Cambiar tema"
        @click="toggleTheme"
        class="rounded-lg border border-app-border p-2.5 text-app-muted transition hover:bg-app-elevated hover:text-brand-500"
      >
        <Sun v-if="theme === 'dark'" :size="18" />
        <Moon v-else :size="18" />
      </button>

      <button
        type="button"
        aria-label="Notificaciones"
        class="relative rounded-lg border border-app-border p-2.5 text-app-muted transition hover:bg-app-elevated hover:text-app-text"
      >
        <Bell :size="18" />
        <span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-brand-500 ring-2 ring-app-surface"></span>
      </button>
    </div>
  </header>
</template>
