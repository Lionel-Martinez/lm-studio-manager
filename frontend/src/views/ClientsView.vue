<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

import { useCsvDownload } from '@/composables/useCsvDownload'
import ProjectPricingDialog from '@/components/clients/ProjectPricingDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { useClientsStore } from '@/stores/clients'
import { deactivationReasonLabels, type Client, type ClientProject, type DeactivationReason } from '@/types/client'
import { formatCurrency, formatShortDate } from '@/utils/dates'

const store = useClientsStore()
const csv = useCsvDownload()

onMounted(() => store.fetchClients(1))

// La búsqueda espera a que termines de escribir antes de consultar el backend.
let searchTimer: ReturnType<typeof setTimeout> | undefined
watch(
  () => store.search,
  () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => store.fetchClients(1), 300)
  },
)
watch(() => store.statusFilter, () => store.fetchClients(1))
onBeforeUnmount(() => {
  clearTimeout(searchTimer)
  // Al salir de la pantalla no debe quedar ningún diálogo abierto en el store.
  store.closePricingDialog()
  store.closeProjectDialog()
})

const statusOptions = [
  { value: '', label: 'Todos los estados' },
  { value: 'new', label: 'Nuevos' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' },
]
const reasonOptions = (Object.keys(deactivationReasonLabels) as DeactivationReason[]).map((value) => ({ value, label: deactivationReasonLabels[value] }))

const toDeactivate = ref<Client | null>(null)
const reason = ref<DeactivationReason>('non_payment')
const reasonNote = ref('')
const deactivating = ref(false)
const statusError = ref('')

function askDeactivate(client: Client) {
  toDeactivate.value = client
  reason.value = 'non_payment'
  reasonNote.value = ''
  statusError.value = ''
}

async function confirmDeactivate() {
  if (!toDeactivate.value) return
  deactivating.value = true
  statusError.value = ''
  try {
    await store.deactivateClient(toDeactivate.value.id, reason.value, reasonNote.value.trim() || null)
    toDeactivate.value = null
  } catch {
    statusError.value = 'No se pudo desactivar el cliente. Intentá de nuevo.'
  } finally {
    deactivating.value = false
  }
}

async function reactivate(client: Client) {
  statusError.value = ''
  try {
    await store.reactivateClient(client.id)
  } catch {
    statusError.value = 'No se pudo reactivar el cliente. Intentá de nuevo.'
  }
}

/** El proyecto que más importa de un cliente: el más reciente que todavía no se entregó. */
function mainProject(client: Client): ClientProject | undefined {
  return client.projects.find((p) => p.status !== 'delivered') ?? client.projects[0]
}

/** Segunda línea de la fila: la persona de contacto (si el cliente es un negocio) y cómo ubicarla. */
function contactLine(client: Client): string {
  const person = client.company_name ? `${client.first_name} ${client.last_name}` : null
  return [person, client.location_label, client.whatsapp ?? client.phone, client.email].filter(Boolean).join(' · ') || 'Sin datos de contacto'
}

const expanded = ref<Set<number>>(new Set())
function toggleProjects(id: number) {
  const next = new Set(expanded.value)
  if (next.has(id)) next.delete(id)
  else next.add(id)
  expanded.value = next
}

const projectToDelete = ref<{ id: number; label: string } | null>(null)
const deletingProject = ref(false)

async function confirmDeleteProject() {
  if (!projectToDelete.value) return
  deletingProject.value = true
  try {
    await store.deleteProject(projectToDelete.value.id)
    projectToDelete.value = null
  } finally {
    deletingProject.value = false
  }
}

const toDelete = ref<Client | null>(null)
const deleting = ref(false)
const deleteError = ref('')

async function confirmDelete() {
  if (!toDelete.value) return
  deleting.value = true
  deleteError.value = ''

  try {
    await store.deleteClient(toDelete.value.id)
    toDelete.value = null
  } catch {
    deleteError.value = 'No se pudo eliminar el cliente. Intentá de nuevo.'
  } finally {
    deleting.value = false
  }
}

const formatDate = (value: string | null) => (value ? formatShortDate(value) : '—')
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <label class="w-full sm:w-80">
        <span class="sr-only">Buscar clientes</span>
        <InputText v-model="store.search" placeholder="Buscar por nombre, empresa, correo o teléfono" fluid />
      </label>

      <div class="flex flex-wrap items-center gap-4 text-xs text-app-muted">
        <Select v-model="store.statusFilter" :options="statusOptions" option-label="label" option-value="value" aria-label="Filtrar por estado" placeholder="Todos los estados" size="small" />
        <p v-if="store.meta">{{ store.meta.total }} {{ store.meta.total === 1 ? 'cliente' : 'clientes' }}</p>
        <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-text" :disabled="csv.downloading.value !== null" @click="csv.download('clients')">
          Exportar clientes (CSV)
        </button>
        <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-text" :disabled="csv.downloading.value !== null" @click="csv.download('projects')">
          Exportar proyectos (CSV)
        </button>
      </div>
    </div>

    <p v-if="csv.error.value" class="text-sm text-app-danger" role="alert">{{ csv.error.value }}</p>
    <p v-if="statusError" class="text-sm text-app-danger" role="alert">{{ statusError }}</p>

    <p v-if="store.loadError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
      {{ store.loadError }}
    </p>

    <EmptyState
      v-else-if="!store.loading && store.items.length === 0"
      :title="store.search ? 'No hay clientes que coincidan' : 'Todavía no registraste clientes'"
      :description="store.search ? 'Probá con otro nombre, empresa o teléfono.' : 'Cargá el primero con su servicio y las fechas de ingreso, inicio y entrega.'"
    >
      <Button v-if="!store.search" label="Nuevo cliente" @click="store.openDialog()" />
    </EmptyState>

    <div v-else class="overflow-x-auto">
      <table class="w-full min-w-[900px] border-collapse text-sm">
        <thead>
          <tr class="border-b-2 border-app-rule text-left text-xs font-medium text-app-muted">
            <th scope="col" class="py-2 pr-4 font-medium">Cliente</th>
            <th scope="col" class="py-2 pr-4 font-medium">Servicio</th>
            <th scope="col" class="py-2 pr-4 font-medium">Estado</th>
            <th scope="col" class="py-2 pr-4 font-medium">Ingresó</th>
            <th scope="col" class="py-2 pr-4 font-medium">Inicio</th>
            <th scope="col" class="py-2 pr-4 font-medium">Entrega</th>
            <th scope="col" class="py-2 font-medium"><span class="sr-only">Acciones</span></th>
          </tr>
        </thead>

        <tbody>
          <template v-for="client in store.items" :key="client.id">
          <tr class="border-b border-app-border align-top">
            <td class="py-3 pr-4">
              <p class="font-semibold">
                {{ client.display_name }}
                <span class="ml-1 text-xs font-normal text-app-muted">{{ client.commercial_status_label }}</span>
              </p>
              <p class="text-xs text-app-muted">{{ contactLine(client) }}</p>
              <p v-if="client.commercial_status === 'inactive' && client.deactivation_reason_label" class="text-xs text-app-danger">
                Desactivado: {{ client.deactivation_reason_label }}<template v-if="client.deactivation_note"> — {{ client.deactivation_note }}</template>
              </p>
            </td>

            <template v-if="mainProject(client)">
              <td class="py-3 pr-4">
                {{ mainProject(client)!.service_type_label }}
                <p v-if="client.projects.length > 1" class="text-xs text-app-muted">
                  +{{ client.projects.length - 1 }} más
                </p>
              </td>
              <td class="py-3 pr-4">{{ mainProject(client)!.status_label }}</td>
              <td class="tabular py-3 pr-4">{{ formatDate(mainProject(client)!.entry_date) }}</td>
              <td class="tabular py-3 pr-4">{{ formatDate(mainProject(client)!.estimated_start_date) }}</td>
              <td class="tabular py-3 pr-4">
                {{ formatDate(mainProject(client)!.due_date) }}
                <p v-if="mainProject(client)!.delay_reason" class="max-w-52 text-xs text-app-danger">
                  Demora: {{ mainProject(client)!.delay_reason }}
                </p>
              </td>
            </template>

            <td v-else colspan="5" class="py-3 text-xs text-app-muted">Sin proyectos registrados</td>

            <td class="whitespace-nowrap py-3 text-right text-xs">
              <button type="button" class="mr-3 cursor-pointer underline underline-offset-4 hover:text-app-accent" :aria-expanded="expanded.has(client.id)" @click="toggleProjects(client.id)">
                Proyectos ({{ client.projects.length }})
              </button>
              <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" :aria-label="`Editar ${client.display_name}`" @click="store.openDialog(client)">Editar</button>
              <button v-if="client.commercial_status !== 'inactive'" type="button" class="ml-3 cursor-pointer underline underline-offset-4 hover:text-app-accent" :aria-label="`Desactivar ${client.display_name}`" @click="askDeactivate(client)">Desactivar</button>
              <button v-else type="button" class="ml-3 cursor-pointer underline underline-offset-4 hover:text-app-accent" :aria-label="`Reactivar ${client.display_name}`" @click="reactivate(client)">Reactivar</button>
              <button type="button" class="ml-3 cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" :aria-label="`Eliminar ${client.display_name}`" @click="toDelete = client; deleteError = ''">Eliminar</button>
            </td>
          </tr>

          <tr v-if="expanded.has(client.id)" class="border-b border-app-border bg-app-sunken">
            <td colspan="7" class="px-4 py-3">
              <ul class="divide-y divide-app-border text-sm">
                <li v-for="project in client.projects" :key="project.id" class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-2">
                  <span>
                    <span class="font-medium">{{ project.name || project.service_type_label }}</span>
                    <span class="ml-2 text-xs text-app-muted">{{ project.service_type_label }} · {{ project.status_label }}</span>
                    <span class="tabular ml-2 text-xs text-app-muted">
                      {{ formatDate(project.entry_date) }} → {{ formatDate(project.estimated_start_date) }} → {{ formatDate(project.due_date) }}
                    </span>
                    <span v-if="project.delay_reason" class="ml-2 text-xs text-app-danger">Demora: {{ project.delay_reason }}</span>
                    <span v-if="project.total_price || project.monthly_fee" class="tabular ml-2 text-xs text-app-muted">
                      {{ [project.total_price ? `${formatCurrency(project.total_price)} (${project.payment_mode_label?.toLowerCase()})` : null, project.monthly_fee ? `${formatCurrency(project.monthly_fee)}/mes` : null].filter(Boolean).join(' + ') }}
                    </span>
                  </span>
                  <span class="whitespace-nowrap text-xs">
                    <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="store.openPricingDialog(client, project)">Precio y pagos</button>
                    <button type="button" class="ml-3 cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="store.openProjectDialog(client, project)">Editar</button>
                    <button type="button" class="ml-3 cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" @click="projectToDelete = { id: project.id, label: project.name || project.service_type_label }">Eliminar</button>
                  </span>
                </li>
                <li v-if="client.projects.length === 0" class="py-2 text-xs text-app-muted">Sin proyectos registrados.</li>
              </ul>
              <Button label="Agregar proyecto" size="small" severity="secondary" class="mt-2" @click="store.openProjectDialog(client)" />
            </td>
          </tr>
          </template>
        </tbody>
      </table>

      <div v-if="store.meta && store.meta.current_page < store.meta.last_page" class="pt-4 text-center">
        <Button label="Cargar más" severity="secondary" text :loading="store.loading" @click="store.loadMore()" />
      </div>
    </div>
  </div>

  <ProjectPricingDialog />

  <Dialog :visible="projectToDelete !== null" modal header="Eliminar proyecto" :draggable="false" :style="{ width: '28rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) projectToDelete = null }">
    <p class="text-sm">¿Eliminar el proyecto <strong>{{ projectToDelete?.label }}</strong>? Se oculta de las listas pero se conserva en la base de datos.</p>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="deletingProject" @click="projectToDelete = null" />
      <Button label="Eliminar" severity="danger" :loading="deletingProject" @click="confirmDeleteProject" />
    </template>
  </Dialog>

  <Dialog :visible="toDeactivate !== null" modal header="Desactivar cliente" :draggable="false" :style="{ width: '30rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) toDeactivate = null }">
    <p class="text-sm">
      <strong>{{ toDeactivate?.display_name }}</strong> queda como inactivo, pero no se borra nada: sus proyectos, pagos y notas se conservan. Sus entregas dejan de aparecer en la agenda; los cobros pendientes siguen apareciendo. Podés reactivarlo cuando quieras.
    </p>
    <div class="mt-4 space-y-3">
      <label class="block text-sm font-medium" for="deact-reason">Motivo</label>
      <Select v-model="reason" input-id="deact-reason" :options="reasonOptions" option-label="label" option-value="value" fluid />
      <label class="block text-sm font-medium" for="deact-note">Nota (opcional)</label>
      <Textarea id="deact-note" v-model="reasonNote" rows="2" maxlength="300" fluid />
    </div>
    <p v-if="statusError" class="mt-3 text-sm text-app-danger" role="alert">{{ statusError }}</p>
    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="deactivating" @click="toDeactivate = null" />
      <Button label="Desactivar" :loading="deactivating" @click="confirmDeactivate" />
    </template>
  </Dialog>

  <Dialog :visible="toDelete !== null" modal header="Eliminar cliente" :draggable="false" :style="{ width: '28rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) toDelete = null }">
    <p class="text-sm">
      ¿Eliminar a <strong>{{ toDelete?.display_name }}</strong>? Deja de verse en la lista, pero sus proyectos y su historial se conservan en la base de datos.
    </p>
    <p v-if="deleteError" class="mt-3 text-sm text-app-danger" role="alert">{{ deleteError }}</p>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="deleting" @click="toDelete = null" />
      <Button label="Eliminar" severity="danger" :loading="deleting" @click="confirmDelete" />
    </template>
  </Dialog>
</template>
