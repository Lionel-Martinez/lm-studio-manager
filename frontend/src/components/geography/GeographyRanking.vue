<script setup lang="ts">
import { ChevronRight } from 'lucide-vue-next'

import EmptyState from '@/components/ui/EmptyState.vue'
import SectionBlock from '@/components/ui/SectionBlock.vue'
import { formatCurrency } from '@/utils/dates'

export interface GeographyRankingItem {
  id: number | string
  name: string
  clients: number
  percentage: number
  revenue: number
}

defineProps<{
  title: string
  items: GeographyRankingItem[]
  clickable?: boolean
}>()

const emit = defineEmits<{
  select: [item: GeographyRankingItem]
}>()
</script>

<template>
  <SectionBlock :title="title" description="Distribución de clientes e ingresos por ubicación.">
    <EmptyState
      v-if="items.length === 0"
      title="No hay ubicaciones registradas"
      description="Cuando registres clientes, van a aparecer acá."
    />

    <ul v-else class="border-t-2 border-app-rule">
      <li v-for="item in items" :key="item.id" class="border-b border-app-border">
        <button
          type="button"
          :disabled="!clickable"
          class="flex w-full items-center gap-4 py-3 text-left transition-colors disabled:cursor-default"
          :class="clickable ? 'cursor-pointer hover:bg-app-elevated' : ''"
          @click="emit('select', item)"
        >
          <div class="min-w-0 flex-1">
            <div class="mb-2 flex items-center justify-between gap-3">
              <span class="truncate text-sm font-semibold">{{ item.name }}</span>
              <span class="shrink-0 text-xs text-app-muted">{{ item.clients }} clientes</span>
            </div>

            <div class="h-1.5 bg-app-elevated">
              <div
                class="h-full bg-app-accent"
                :style="{ width: `${Math.min(100, Math.max(0, item.percentage))}%` }"
              ></div>
            </div>

            <div class="tabular mt-2 flex items-center justify-between gap-2 text-xs">
              <span class="text-app-muted">{{ item.percentage.toFixed(1) }}%</span>
              <span class="font-semibold">{{ formatCurrency(item.revenue) }}</span>
            </div>
          </div>

          <ChevronRight v-if="clickable" :size="16" class="shrink-0 text-app-muted" aria-hidden="true" />
        </button>
      </li>
    </ul>
  </SectionBlock>
</template>
