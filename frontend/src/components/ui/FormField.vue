<script setup lang="ts">
defineProps<{
  label: string
  for: string
  error?: string
  hint?: string
  required?: boolean
}>()
</script>

<template>
  <div class="flex min-w-0 flex-col gap-1.5">
    <label :for="$props.for" class="text-sm font-medium">
      {{ label }}<span v-if="required" class="text-app-danger" aria-hidden="true"> *</span>
    </label>

    <slot />

    <p v-if="error" class="text-xs text-app-danger" role="alert">{{ error }}</p>
    <p v-else-if="hint" class="text-xs text-app-muted">{{ hint }}</p>
  </div>
</template>
