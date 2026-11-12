<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'

export interface GeographyBreadcrumbItem {
  label: string
  id: number | string
}

defineProps<{
  items: GeographyBreadcrumbItem[]
}>()

const emit = defineEmits<{
  navigate: [index: number]
}>()
</script>

<template>
  <nav aria-label="Ruta geográfica" class="flex flex-wrap items-center gap-2 text-sm">
    <button
      type="button"
      class="cursor-pointer transition-colors hover:text-app-accent"
      :class="items.length === 0 ? 'font-semibold text-app-text' : 'text-app-muted'"
      :aria-current="items.length === 0 ? 'page' : undefined"
      @click="emit('navigate', 0)"
    >
      Países
    </button>

    <template v-for="(item, index) in items" :key="item.id">
      <ChevronRight :size="14" class="text-app-muted" aria-hidden="true" />

      <button
        type="button"
        class="cursor-pointer transition-colors"
        :class="
          index === items.length - 1
            ? 'font-semibold text-app-text'
            : 'text-app-muted hover:text-app-accent'
        "
        :aria-current="index === items.length - 1 ? 'page' : undefined"
        @click="emit('navigate', index + 1)"
      >
        {{ item.label }}
      </button>
    </template>
  </nav>
</template>
