<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()

const today = new Intl.DateTimeFormat('es-AR', {
  weekday: 'long',
  day: 'numeric',
  month: 'long',
  year: 'numeric',
}).format(new Date())
</script>

<template>
  <header class="border-b border-app-rule px-5 pb-6 pt-8 sm:px-8 lg:px-12 lg:pt-9">
    <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-4">
      <div>
        <p class="text-xs text-app-muted first-letter:uppercase">{{ today }}</p>

        <h1 class="mt-1.5 font-display text-4xl font-medium leading-none tracking-tight sm:text-5xl">
          {{ title }}
        </h1>

        <p v-if="description" class="mt-3 text-sm text-app-muted">
          {{ description }}
        </p>
      </div>

      <div v-if="$slots.actions">
        <slot name="actions" />
      </div>
    </div>
  </header>
</template>
