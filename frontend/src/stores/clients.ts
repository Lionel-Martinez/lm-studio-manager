import { ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/lib/api'
import type { Client, NewClientPayload, PaginationMeta } from '@/types/client'

export const useClientsStore = defineStore('clients', () => {
  const items = ref<Client[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const search = ref('')
  const loading = ref(false)
  const loadError = ref<string | null>(null)

  const dialogOpen = ref(false)

  /** Carga una página. La página 1 reemplaza la lista; las siguientes se agregan al final. */
  async function fetchClients(page = 1) {
    loading.value = true
    loadError.value = null

    try {
      const { data } = await api.get('/clients', {
        params: { page, search: search.value.trim() || undefined },
      })

      items.value = page === 1 ? data.data : [...items.value, ...data.data]
      meta.value = data.meta
    } catch {
      loadError.value = 'No se pudieron cargar los clientes. Verificá que el backend esté en marcha.'
    } finally {
      loading.value = false
    }
  }

  async function loadMore() {
    if (meta.value && meta.value.current_page < meta.value.last_page) {
      await fetchClients(meta.value.current_page + 1)
    }
  }

  /** Crea el cliente (y su primer proyecto, si viene). Los errores se propagan al formulario. */
  async function createClient(payload: NewClientPayload): Promise<Client> {
    const { data } = await api.post('/clients', payload)
    await fetchClients(1)
    return data.data
  }

  function openDialog() {
    dialogOpen.value = true
  }

  function closeDialog() {
    dialogOpen.value = false
  }

  return {
    items,
    meta,
    search,
    loading,
    loadError,
    dialogOpen,
    fetchClients,
    loadMore,
    createClient,
    openDialog,
    closeDialog,
  }
})
