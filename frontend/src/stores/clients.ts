import { ref } from 'vue'
import { defineStore } from 'pinia'

import { api } from '@/lib/api'
import type { Client, ClientDataPayload, ClientProject, NewClientPayload, NewProjectPayload, PaginationMeta } from '@/types/client'

export const useClientsStore = defineStore('clients', () => {
  const items = ref<Client[]>([])
  const meta = ref<PaginationMeta | null>(null)
  const search = ref('')
  const loading = ref(false)
  const loadError = ref<string | null>(null)

  const dialogOpen = ref(false)
  /** Cliente que se está editando; `null` cuando el diálogo es para crear uno nuevo. */
  const editing = ref<Client | null>(null)

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

  async function updateClient(id: number, payload: ClientDataPayload): Promise<Client> {
    const { data } = await api.put(`/clients/${id}`, payload)
    await fetchClients(1)
    return data.data
  }

  /** Baja lógica en el backend: el cliente se oculta pero su historial se conserva. */
  async function deleteClient(id: number) {
    await api.delete(`/clients/${id}`)
    await fetchClients(1)
  }

  /** Diálogo de proyecto: crea uno para `clientId`, o edita `project` si viene. */
  const projectDialog = ref<{ clientId: number; clientName: string; project: ClientProject | null } | null>(null)

  function openProjectDialog(client: Client, project: ClientProject | null = null) {
    projectDialog.value = { clientId: client.id, clientName: client.display_name, project }
  }

  function closeProjectDialog() {
    projectDialog.value = null
  }

  async function saveProject(payload: NewProjectPayload) {
    const dialog = projectDialog.value
    if (!dialog) return

    if (dialog.project) await api.put(`/projects/${dialog.project.id}`, payload)
    else await api.post(`/clients/${dialog.clientId}/projects`, payload)

    await fetchClients(1)
  }

  async function deleteProject(id: number) {
    await api.delete(`/projects/${id}`)
    await fetchClients(1)
  }

  function openDialog(client: Client | null = null) {
    editing.value = client
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
    editing,
    projectDialog,
    openProjectDialog,
    closeProjectDialog,
    saveProject,
    deleteProject,
    fetchClients,
    loadMore,
    createClient,
    updateClient,
    deleteClient,
    openDialog,
    closeDialog,
  }
})
