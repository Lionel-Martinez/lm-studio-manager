import { ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/lib/api'
import type { Client, PaginationMeta } from '@/types/client'
import type { Note, NotePayload } from '@/types/note'

export const useNotesStore = defineStore('notes', () => {
  const items = ref<Note[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const search = ref('')
  /** 'all', 'general' (sin cliente) o el id de un cliente. */
  const scope = ref<'all' | 'general' | number>('all')
  const loading = ref(false)
  const loadError = ref<string | null>(null)

  const clientOptions = ref<Client[]>([])

  const dialogOpen = ref(false)
  const editing = ref<Note | null>(null)

  async function fetchNotes(page = 1) {
    loading.value = true
    loadError.value = null

    try {
      const { data } = await api.get('/notes', {
        params: {
          page,
          search: search.value.trim() || undefined,
          scope: scope.value === 'general' ? 'general' : undefined,
          client_id: typeof scope.value === 'number' ? scope.value : undefined,
        },
      })
      items.value = page === 1 ? data.data : [...items.value, ...data.data]
      meta.value = data.meta
    } catch {
      loadError.value = 'No se pudieron cargar las notas. Verificá que el backend esté en marcha.'
    } finally {
      loading.value = false
    }
  }

  async function loadMore() {
    if (meta.value && meta.value.current_page < meta.value.last_page) await fetchNotes(meta.value.current_page + 1)
  }

  async function fetchClientOptions() {
    const { data } = await api.get('/clients', { params: { per_page: 100 } })
    clientOptions.value = data.data
  }

  /** Guarda y devuelve la nota para poder seleccionarla. */
  async function saveNote(payload: NotePayload): Promise<Note> {
    const { data } = editing.value ? await api.put(`/notes/${editing.value.id}`, payload) : await api.post('/notes', payload)
    await fetchNotes(1)
    return data.data
  }

  async function deleteNote(id: number) {
    await api.delete(`/notes/${id}`)
    await fetchNotes(1)
  }

  function openDialog(note: Note | null = null) {
    editing.value = note
    dialogOpen.value = true
  }

  function closeDialog() {
    dialogOpen.value = false
  }

  return {
    items, meta, search, scope, loading, loadError, clientOptions, dialogOpen, editing,
    fetchNotes, loadMore, fetchClientOptions, saveNote, deleteNote, openDialog, closeDialog,
  }
})
