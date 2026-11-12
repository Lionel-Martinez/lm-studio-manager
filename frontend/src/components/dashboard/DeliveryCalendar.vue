<script setup lang="ts">
import { computed } from 'vue'

import EmptyState from '@/components/ui/EmptyState.vue'
import { projectStatusLabels, type DeliveryItem } from '@/types/dashboard'
import {
  addDays,
  daysBetween,
  formatShortDate,
  parseISODate,
  startOfDay,
  startOfWeek,
} from '@/utils/dates'

const props = defineProps<{
  projects: DeliveryItem[]
}>()

const WEEKS = 8
const TOTAL_DAYS = WEEKS * 7

const today = startOfDay(new Date())
// La ventana arranca el lunes de la semana pasada, así se ve lo que acaba de entrar.
const windowStart = addDays(startOfWeek(today), -7)
const windowEnd = addDays(windowStart, TOTAL_DAYS) // exclusivo

const weekLabels = Array.from({ length: WEEKS }, (_, i) =>
  formatShortDate(addDays(windowStart, i * 7)),
)

const toPercent = (days: number) => (days / TOTAL_DAYS) * 100
const todayLeft = toPercent(daysBetween(windowStart, today) + 0.5)

interface Row {
  project: DeliveryItem
  hasDates: boolean
  bar: { left: number; width: number } | null
  dates: string
}

const rows = computed<Row[]>(() =>
  props.projects.map((project) => {
    if (!project.startDate || !project.dueDate) {
      return { project, hasDates: false, bar: null, dates: 'Sin fechas definidas' }
    }

    const start = parseISODate(project.startDate)
    const due = parseISODate(project.dueDate)
    const clippedStart = start < windowStart ? windowStart : start
    const clippedEnd = addDays(due, 1) > windowEnd ? windowEnd : addDays(due, 1)
    const width = toPercent(daysBetween(clippedStart, clippedEnd))

    return {
      project,
      hasDates: true,
      bar: width > 0 ? { left: toPercent(daysBetween(windowStart, clippedStart)), width } : null,
      dates: `${formatShortDate(start)} → ${formatShortDate(due)}`,
    }
  }),
)
</script>

<template>
  <EmptyState
    v-if="projects.length === 0"
    title="Todavía no hay proyectos"
    description="Cuando registres clientes con fechas de inicio y entrega, los vas a ver acá."
  />

  <div v-else class="overflow-x-auto">
    <div class="min-w-[720px] border-t-2 border-app-rule">
      <!-- Semanas -->
      <div class="flex border-b border-app-border text-xs text-app-muted">
        <div class="w-52 shrink-0"></div>
        <div class="relative grid flex-1 grid-cols-8">
          <div
            v-for="label in weekLabels"
            :key="label"
            class="border-l border-app-border py-2 pl-2"
          >
            {{ label }}
          </div>
        </div>
      </div>

      <!-- Filas -->
      <ul class="relative">
        <li
          v-for="row in rows"
          :key="row.project.id"
          class="flex min-h-16 items-center border-b border-app-border"
        >
          <div class="w-52 shrink-0 py-2 pr-4">
            <p class="text-sm font-semibold">{{ row.project.client }}</p>
            <p class="text-xs text-app-muted">
              {{ row.project.service }} · {{ projectStatusLabels[row.project.status] }}
            </p>
            <p v-if="row.project.delayReason" class="mt-0.5 text-xs text-app-danger">
              Demora: {{ row.project.delayReason }}
            </p>
          </div>

          <div class="relative h-16 flex-1">
            <div
              v-if="row.bar"
              class="absolute top-5 h-6"
              :class="
                row.project.status === 'waiting'
                  ? 'border-2 border-dashed border-app-accent'
                  : 'bg-app-accent'
              "
              :style="{ left: `${row.bar.left}%`, width: `${row.bar.width}%` }"
            >
              <span class="sr-only">{{ row.dates }}</span>
            </div>

            <p
              v-else
              class="absolute inset-y-0 left-3 flex items-center text-xs text-app-muted"
            >
              {{ row.hasDates ? 'Fuera de este período' : row.dates }}
            </p>
          </div>
        </li>

        <!-- Línea de hoy -->
        <li
          aria-hidden="true"
          class="pointer-events-none absolute inset-y-0 w-0.5 bg-app-danger"
          :style="{ left: `calc(13rem + (100% - 13rem) * ${todayLeft / 100})` }"
        ></li>
      </ul>

      <div class="flex flex-wrap gap-x-5 gap-y-1 pt-3 text-xs text-app-muted">
        <span class="flex items-center gap-1.5"><i class="inline-block size-3 bg-app-accent"></i>En curso</span>
        <span class="flex items-center gap-1.5"><i class="inline-block size-3 border-2 border-dashed border-app-accent"></i>En espera</span>
        <span class="flex items-center gap-1.5"><i class="inline-block h-3 w-0.5 bg-app-danger"></i>Hoy</span>
      </div>
    </div>
  </div>
</template>
