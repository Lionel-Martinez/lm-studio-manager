<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import Select from 'primevue/select'

import ReminderFormDialog from '@/components/agenda/ReminderFormDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useAgendaStore } from '@/stores/agenda'
import type { AgendaEvent, AgendaEventType } from '@/types/agenda'

const store = useAgendaStore()

onMounted(() => store.fetchAgenda())
watch(() => store.days, () => store.fetchAgenda())

const rangeOptions = [
  { value: 7, label: 'Próximos 7 días' },
  { value: 30, label: 'Próximos 30 días' },
  { value: 90, label: 'Próximos 90 días' },
]

const TYPE_LABEL: Record<AgendaEventType, string> = { delivery: 'Entrega', start: 'Inicio', payment: 'Cobro', reminder: 'Recordatorio' }

const toDate = (iso: string) => new Date(`${iso}T00:00:00`)
const todayIso = () => new Date().toLocaleDateString('en-CA')
const dayLabel = (iso: string) => {
  const diff = Math.round((toDate(iso).getTime() - toDate(todayIso()).getTime()) / 86_400_000)
  if (diff === 0) return 'Hoy'
  if (diff === 1) return 'Mañana'
  return new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'long' }).format(toDate(iso))
}
const daysLate = (iso: string) => Math.round((toDate(todayIso()).getTime() - toDate(iso).getTime()) / 86_400_000)
const money = (n: number) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', maximumFractionDigits: 0 }).format(n)

const groups = computed(() => {
  const map = new Map<string, AgendaEvent[]>()
  for (const e of store.agenda.upcoming) map.set(e.date, [...(map.get(e.date) ?? []), e])
  return [...map.entries()].map(([date, events]) => ({ date, events }))
})

const toDelete = ref<AgendaEvent | null>(null)
const deleting = ref(false)
const actionError = ref('')

async function complete(e: AgendaEvent) {
  actionError.value = ''
  try {
    await store.completeReminder(e)
  } catch {
    actionError.value = 'No se pudo marcar como hecho.'
  }
}

async function confirmDelete() {
  if (!toDelete.value) return
  deleting.value = true
  try {
    await store.deleteReminder(toDelete.value.id)
    toDelete.value = null
  } catch {
    actionError.value = 'No se pudo eliminar el recordatorio.'
    toDelete.value = null
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="space-y-8">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <Select v-model="store.days" :options="rangeOptions" option-label="label" option-value="value" aria-label="Rango" />
      <Button label="Nuevo recordatorio" icon="pi pi-plus" @click="store.openDialog()" />
    </div>

    <p v-if="store.loadError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ store.loadError }}</p>
    <p v-if="actionError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ actionError }}</p>

    <section v-if="store.agenda.overdue.length" aria-labelledby="ag-overdue">
      <h2 id="ag-overdue" class="mb-3 font-display text-lg font-medium text-app-danger">Atrasado ({{ store.agenda.overdue.length }})</h2>
      <ul class="divide-y divide-app-border border-y border-app-border">
        <li v-for="e in store.agenda.overdue" :key="e.key" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3">
          <span class="w-28 shrink-0 text-xs uppercase tracking-wide text-app-muted">{{ TYPE_LABEL[e.type] }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ e.title }}</p>
            <p class="text-xs text-app-muted">{{ [e.client, `hace ${daysLate(e.date)} d`].filter(Boolean).join(' · ') }}</p>
          </div>
          <span v-if="e.amount !== null" class="text-sm tabular-nums">{{ money(e.amount) }}</span>
          <div v-if="e.type === 'reminder'" class="flex gap-1">
            <Button label="Hecho" size="small" text @click="complete(e)" />
            <Button icon="pi pi-pencil" size="small" text severity="secondary" aria-label="Editar" @click="store.openDialog(e)" />
            <Button icon="pi pi-trash" size="small" text severity="danger" aria-label="Eliminar" @click="toDelete = e" />
          </div>
        </li>
      </ul>
    </section>

    <section v-for="g in groups" :key="g.date" :aria-label="dayLabel(g.date)">
      <h2 class="mb-3 font-display text-lg font-medium capitalize">{{ dayLabel(g.date) }}</h2>
      <ul class="divide-y divide-app-border border-y border-app-border">
        <li v-for="e in g.events" :key="e.key" class="flex flex-wrap items-center gap-x-4 gap-y-1 py-3">
          <span class="w-28 shrink-0 text-xs uppercase tracking-wide text-app-muted">{{ TYPE_LABEL[e.type] }}</span>
          <div class="min-w-0 flex-1">
            <p class="truncate text-sm font-medium">{{ e.title }}</p>
            <p v-if="e.client || e.notes" class="truncate text-xs text-app-muted">{{ [e.client, e.notes].filter(Boolean).join(' · ') }}</p>
          </div>
          <span v-if="e.amount !== null" class="text-sm tabular-nums">{{ money(e.amount) }}</span>
          <div v-if="e.type === 'reminder'" class="flex gap-1">
            <Button label="Hecho" size="small" text @click="complete(e)" />
            <Button icon="pi pi-pencil" size="small" text severity="secondary" aria-label="Editar" @click="store.openDialog(e)" />
            <Button icon="pi pi-trash" size="small" text severity="danger" aria-label="Eliminar" @click="toDelete = e" />
          </div>
        </li>
      </ul>
    </section>

    <EmptyState v-if="!store.loading && !store.loadError && !store.agenda.overdue.length && !groups.length" title="Agenda al día" description="No hay entregas, cobros ni recordatorios pendientes en este rango.">
      <Button label="Nuevo recordatorio" severity="secondary" @click="store.openDialog()" />
    </EmptyState>

    <ReminderFormDialog />

    <Dialog :visible="toDelete !== null" modal header="Eliminar recordatorio" :draggable="false" :style="{ width: '26rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) toDelete = null }">
      <p class="text-sm">¿Eliminar «{{ toDelete?.title }}»?</p>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text :disabled="deleting" @click="toDelete = null" />
        <Button label="Eliminar" severity="danger" :loading="deleting" @click="confirmDelete" />
      </template>
    </Dialog>
  </div>
</template>
