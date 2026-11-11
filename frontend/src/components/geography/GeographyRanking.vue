<script setup lang="ts">
import { ChevronRight, MapPin } from 'lucide-vue-next'

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
  currency?: string
  clickable?: boolean
}>()

const emit = defineEmits<{
  select: [item: GeographyRankingItem]
}>()

function formatCurrency(value: number, currency: string) {
  return new Intl.NumberFormat('es-AR', {
    style: 'currency',
    currency,
    maximumFractionDigits: 0,
  }).format(value)
}
</script>

<template>
  <section class="overflow-hidden rounded-xl border border-app-border bg-app-surface">
    <div class="border-b border-app-border p-5">
      <h3 class="font-semibold text-app-text">{{ title }}</h3>
      <p class="mt-1 text-sm text-app-muted">
        Distribución de clientes e ingresos por ubicación.
      </p>
    </div>

    <div v-if="items.length === 0" class="flex min-h-48 items-center justify-center p-6 text-center">
      <div>
        <MapPin :size="26" class="mx-auto mb-3 text-app-muted" />
        <p class="text-sm font-medium text-app-text">
          No hay ubicaciones registradas
        </p>
        <p class="mt-1 text-xs text-app-muted">
          Cuando registres clientes, aparecerán aquí.
        </p>
      </div>
    </div>

    <div v-else class="divide-y divide-app-border">
      <button
        v-for="item in items"
        :key="item.id"
        type="button"
        :disabled="!clickable"
        class="flex w-full items-center gap-4 p-4 text-left transition-colors"
        :class="clickable ? 'hover:bg-app-elevated' : 'cursor-default'"
        @click="emit('select', item)"
      >
        <div class="min-w-0 flex-1">
          <div class="mb-2 flex items-center justify-between gap-3">
            <span class="truncate text-sm font-medium text-app-text">
              {{ item.name }}
            </span>
            <span class="shrink-0 text-xs text-app-muted">
              {{ item.clients }} clientes
            </span>
          </div>

          <div class="h-1.5 overflow-hidden rounded-full bg-app-elevated">
            <div
              class="h-full rounded-full bg-brand-500 transition-all"
              :style="{ width: `${Math.min(100, Math.max(0, item.percentage))}%` }"
            />
          </div>

          <div class="mt-2 flex items-center justify-between gap-2">
            <span class="text-xs text-app-muted">
              {{ item.percentage.toFixed(1) }}%
            </span>
            <span class="text-xs font-medium text-app-text">
              {{ formatCurrency(item.revenue, currency ?? 'ARS') }}
            </span>
          </div>
        </div>

        <ChevronRight
          v-if="clickable"
          :size="17"
          class="shrink-0 text-app-muted"
        />
      </button>
    </div>
  </section>
</template>
