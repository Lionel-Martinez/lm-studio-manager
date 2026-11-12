<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <section class="min-w-0">
    <div class="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
      <div>
        <h2 class="font-display text-2xl font-medium tracking-tight">{{ title }}</h2>
        <p v-if="description" class="mt-0.5 text-xs text-app-muted">{{ description }}</p>
      </div>

      <div v-if="$slots.aside" class="text-xs text-app-muted">
        <slot name="aside" />
      </div>
    </div>

    <slot />
  </section>
</template>
