<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'

import EmptyState from '@/components/ui/EmptyState.vue'
import { useClientsStore } from '@/stores/clients'
import type { Client, ClientProject } from '@/types/client'
import { formatShortDate } from '@/utils/dates'

const store = useClientsStore()

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
onBeforeUnmount(() => clearTimeout(searchTimer))

/** El proyecto que más importa de un cliente: el más reciente que todavía no se entregó. */
function mainProject(client: Client): ClientProject | undefined {
  return client.projects.find((p) => p.status !== 'delivered') ?? client.projects[0]
}

/** Segunda línea de la fila: la persona de contacto (si el cliente es un negocio) y cómo ubicarla. */
function contactLine(client: Client): string {
  const person = client.company_name ? `${client.first_name} ${client.last_name}` : null
  return [person, client.whatsapp ?? client.phone, client.email].filter(Boolean).join(' · ') || 'Sin datos de contacto'
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

      <p v-if="store.meta" class="text-xs text-app-muted">
        {{ store.meta.total }} {{ store.meta.total === 1 ? 'cliente' : 'clientes' }}
      </p>
    </div>

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
      <table class="w-full min-w-[840px] border-collapse text-sm">
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
          <tr v-for="client in store.items" :key="client.id" class="border-b border-app-border align-top">
            <td class="py-3 pr-4">
              <p class="font-semibold">{{ client.display_name }}</p>
              <p class="text-xs text-app-muted">{{ contactLine(client) }}</p>
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
              <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" :aria-label="`Editar ${client.display_name}`" @click="store.openDialog(client)">Editar</button>
              <button type="button" class="ml-3 cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" :aria-label="`Eliminar ${client.display_name}`" @click="toDelete = client; deleteError = ''">Eliminar</button>
            </td>
          </tr>
        </tbody>
      </table>

      <div v-if="store.meta && store.meta.current_page < store.meta.last_page" class="pt-4 text-center">
        <Button label="Cargar más" severity="secondary" text :loading="store.loading" @click="store.loadMore()" />
      </div>
    </div>
  </div>

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
