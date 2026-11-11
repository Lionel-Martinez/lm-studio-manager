<script setup lang="ts">
import { ChevronRight, Globe2 } from 'lucide-vue-next'

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
  <nav aria-label="Ruta geográfica" class="flex flex-wrap items-center gap-2">
    <button
      type="button"
      class="flex items-center gap-2 text-sm font-medium text-app-muted transition hover:text-brand-500"
      @click="emit('navigate', 0)"
    >
      <Globe2 :size="16" />
      Países
    </button>

    <template v-for="(item, index) in items" :key="item.id">
      <ChevronRight :size="15" class="text-app-muted" />

      <button
        type="button"
        class="text-sm font-medium transition"
        :class="
          index === items.length - 1
            ? 'text-app-text'
            : 'text-app-muted hover:text-brand-500'
        "
        :aria-current="index === items.length - 1 ? 'page' : undefined"
        @click="emit('navigate', index + 1)"
      >
        {{ item.label }}
      </button>
    </template>
  </nav>
</template>
