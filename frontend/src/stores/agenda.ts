import { ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/lib/api'
import type { Agenda, AgendaEvent, AgendaSummary, ReminderPayload } from '@/types/agenda'
import type { Client } from '@/types/client'

export const useAgendaStore = defineStore('agenda', () => {
  const agenda = ref<Agenda>({ overdue: [], upcoming: [] })
  const summary = ref<AgendaSummary>({ overdue: 0, today: 0 })
  const days = ref(30)
  const loading = ref(false)
  const loadError = ref<string | null>(null)

  const clientOptions = ref<Client[]>([])
  const dialogOpen = ref(false)
  const editing = ref<AgendaEvent | null>(null)

  async function fetchAgenda() {
    loading.value = true
    loadError.value = null
    try {
      const { data } = await api.get('/agenda', { params: { days: days.value } })
      agenda.value = data.data
    } catch {
      loadError.value = 'No se pudo cargar la agenda. Verificá que el backend esté en marcha.'
    } finally {
      loading.value = false
    }
  }

  async function fetchSummary() {
    try {
      const { data } = await api.get('/agenda/summary')
      summary.value = data.data
    } catch {
      // La insignia es opcional: si falla, simplemente no se muestra.
    }
  }

  async function fetchClientOptions() {
    const { data } = await api.get('/clients', { params: { per_page: 100 } })
    clientOptions.value = data.data
  }

  async function saveReminder(payload: ReminderPayload) {
    if (editing.value) await api.put(`/reminders/${editing.value.id}`, payload)
    else await api.post('/reminders', payload)
    await Promise.all([fetchAgenda(), fetchSummary()])
  }

  async function completeReminder(event: AgendaEvent) {
    await api.put(`/reminders/${event.id}`, {
      client_id: event.client_id ?? null,
      title: event.title,
      notes: event.notes ?? null,
      remind_on: event.date,
      done: true,
    })
    await Promise.all([fetchAgenda(), fetchSummary()])
  }

  async function deleteReminder(id: number) {
    await api.delete(`/reminders/${id}`)
    await Promise.all([fetchAgenda(), fetchSummary()])
  }

  function openDialog(event: AgendaEvent | null = null) {
    editing.value = event
    dialogOpen.value = true
  }

  function closeDialog() {
    dialogOpen.value = false
  }

  return {
    agenda, summary, days, loading, loadError, clientOptions, dialogOpen, editing,
    fetchAgenda, fetchSummary, fetchClientOptions, saveReminder, completeReminder, deleteReminder, openDialog, closeDialog,
  }
})
