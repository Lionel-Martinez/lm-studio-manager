<script setup lang="ts">
defineProps<{
  title: string
  description?: string
}>()
</script>

<template>
  <div class="border-y border-app-border py-12 text-center">
    <p class="font-display text-xl font-medium">{{ title }}</p>
    <p v-if="description" class="mx-auto mt-2 max-w-md text-sm text-app-muted">
      {{ description }}
    </p>
    <div v-if="$slots.default" class="mt-5">
      <slot />
    </div>
  </div>
</template>
