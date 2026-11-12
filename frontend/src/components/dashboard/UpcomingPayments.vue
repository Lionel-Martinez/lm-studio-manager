<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import type { PendingPayment } from '@/types/dashboard'
import { formatCurrency, formatShortDate, parseISODate, startOfDay } from '@/utils/dates'

const props = defineProps<{
  payments: PendingPayment[]
}>()

const items = computed(() => {
  const today = startOfDay(new Date())

  return [...props.payments]
    .sort((a, b) => a.dueDate.localeCompare(b.dueDate))
    .map((payment) => ({
      ...payment,
      overdue: parseISODate(payment.dueDate) < today,
      date: formatShortDate(payment.dueDate),
    }))
})
</script>

<template>
  <EmptyState
    v-if="items.length === 0"
    title="No hay cobros pendientes"
    description="Los pagos por vencer van a aparecer acá, ordenados por fecha."
  />

  <ul v-else class="border-t-2 border-app-rule">
    <li
      v-for="item in items"
      :key="item.id"
      class="flex items-start gap-4 border-b border-app-border py-3"
    >
      <div class="w-16 shrink-0 text-sm font-semibold" :class="item.overdue ? 'text-app-danger' : ''">
        {{ item.date }}
        <span v-if="item.overdue" class="block text-xs font-normal">vencido</span>
      </div>

      <div class="min-w-0 flex-1">
        <p class="truncate text-sm">{{ item.client }}</p>
        <p class="text-xs text-app-muted">{{ item.concept }}</p>
      </div>

      <p class="tabular text-sm font-semibold">{{ formatCurrency(item.amount) }}</p>
    </li>
  </ul>
</template>
