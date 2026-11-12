<script setup lang="ts">
import { computed } from 'vue'
import { ArcElement, Chart as ChartJS, Tooltip } from 'chart.js'
import { Doughnut } from 'vue-chartjs'

import EmptyState from '@/components/ui/EmptyState.vue'
import { useChartColors } from '@/composables/useChartColors'
import type { ClientDistributionPoint } from '@/types/dashboard'

ChartJS.register(ArcElement, Tooltip)

const props = defineProps<{
  points: ClientDistributionPoint[]
}>()

const colors = useChartColors()

const hasData = computed(() => props.points.some((p) => p.value > 0))
const total = computed(() => props.points.reduce((sum, p) => sum + p.value, 0))

const seriesColor = (index: number) => colors.value.series[index % colors.value.series.length]

const chartData = computed(() => ({
  labels: props.points.map((p) => p.label),
  datasets: [
    {
      data: props.points.map((p) => p.value),
      backgroundColor: props.points.map((_, i) => seriesColor(i)),
      borderColor: colors.value.surface,
      borderWidth: 2,
    },
  ],
}))

const chartOptions = computed(() => ({
  responsive: true,
  maintainAspectRatio: false,
  cutout: '68%',
  plugins: {
    legend: { display: false },
    tooltip: {
      backgroundColor: colors.value.text,
      titleColor: colors.value.surface,
      bodyColor: colors.value.surface,
      cornerRadius: 2,
      displayColors: false,
      callbacks: {
        label: (context: { label: string; parsed: number }) =>
          `${context.label}: ${context.parsed} clientes`,
      },
    },
  },
}))
</script>

<template>
  <EmptyState
    v-if="!hasData"
    title="Todavía no hay clientes registrados"
    description="Acá aparece la distribución por ubicación."
  />

  <div v-else class="flex flex-wrap items-center gap-6 border-t-2 border-app-rule pt-4">
    <div class="relative size-44 shrink-0">
      <Doughnut :data="chartData" :options="chartOptions" />

      <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
        <span class="tabular font-display text-3xl font-medium">{{ total }}</span>
        <span class="text-xs text-app-muted">clientes</span>
      </div>
    </div>

    <ul class="min-w-40 flex-1 space-y-2 text-sm">
      <li v-for="(point, index) in points" :key="point.label" class="flex items-center gap-2">
        <i class="inline-block size-3 shrink-0" :style="{ backgroundColor: seriesColor(index) }"></i>
        <span class="flex-1 truncate">{{ point.label }}</span>
        <span class="tabular text-app-muted">{{ point.value }}</span>
      </li>
    </ul>
  </div>
</template>
