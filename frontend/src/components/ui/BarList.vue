<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/components/ui/EmptyState.vue'

const props = defineProps<{
  items: { label: string; value: number }[]
  /** Cómo mostrar cada valor (por defecto, número entero). */
  format?: (value: number) => string
  emptyTitle?: string
}>()

const max = computed(() => Math.max(...props.items.map((i) => i.value), 0))
const hasData = computed(() => max.value > 0)
const show = (value: number) => (props.format ? props.format(value) : String(value))
</script>

<template>
  <ul v-if="hasData" class="space-y-3 text-sm">
    <li v-for="item in items" :key="item.label">
      <div class="flex items-baseline justify-between gap-3">
        <span class="truncate">{{ item.label }}</span>
        <span class="tabular shrink-0 font-medium">{{ show(item.value) }}</span>
      </div>
      <div class="mt-1 h-1.5 bg-app-sunken" role="presentation">
        <div class="h-full bg-app-accent" :style="{ width: `${(item.value / max) * 100}%` }" />
      </div>
    </li>
  </ul>

  <EmptyState v-else :title="emptyTitle ?? 'Sin datos en este período'" description="Cuando haya movimientos aparecen acá." />
</template>
