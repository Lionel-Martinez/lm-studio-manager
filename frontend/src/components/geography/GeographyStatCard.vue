<script setup lang="ts">
defineProps<{
  title: string
  value: string | number
  description: string
}>()
</script>

<template>
  <div class="bg-app-bg px-5 py-6">
    <dt class="text-xs text-app-muted">{{ title }}</dt>
    <dd class="tabular mt-2 font-display text-3xl font-medium tracking-tight sm:text-4xl">
      {{ value }}
    </dd>
    <p class="mt-1 text-xs text-app-muted">{{ description }}</p>
  </div>
</template>
