<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import { projectStatusLabels, type DeliveryItem } from '@/types/dashboard'
import { formatShortDate } from '@/utils/dates'

const props = defineProps<{
  items: DeliveryItem[]
}>()

const rows = computed(() =>
  [...props.items]
    .sort((a, b) => (a.startDate ?? '9999').localeCompare(b.startDate ?? '9999'))
    .map((item) => ({
      ...item,
      start: item.startDate ? formatShortDate(item.startDate) : 'A definir',
      entry: formatShortDate(item.entryDate),
      statusLabel: projectStatusLabels[item.status],
    })),
)
</script>

<template>
  <EmptyState
    v-if="rows.length === 0"
    title="La lista de espera está vacía"
    description="Los clientes nuevos que todavía no empezaron aparecen acá con su fecha estimada de inicio."
  />

  <ul v-else class="border-t-2 border-app-rule">
    <li
      v-for="row in rows"
      :key="row.id"
      class="flex items-start gap-4 border-b border-app-border py-3"
    >
      <div class="w-16 shrink-0 text-sm font-semibold" :class="row.startDate ? '' : 'text-app-muted'">
        {{ row.start }}
        <span v-if="row.startDate" class="block text-xs font-normal text-app-muted">inicio</span>
      </div>

      <div class="min-w-0 flex-1">
        <p class="truncate text-sm">{{ row.client }}</p>
        <p class="text-xs text-app-muted">
          {{ row.service }} · ingresó {{ row.entry }}
        </p>
      </div>

      <p class="text-xs text-app-muted">{{ row.statusLabel }}</p>
    </li>
  </ul>
</template>
