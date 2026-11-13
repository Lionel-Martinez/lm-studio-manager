<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

import NoteFormDialog from '@/components/notes/NoteFormDialog.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import { renderMarkdown } from '@/lib/markdown'
import { useNotesStore } from '@/stores/notes'
import type { Note } from '@/types/note'

const store = useNotesStore()

const selectedId = ref<number | null>(null)
const selected = computed(() => store.items.find((n) => n.id === selectedId.value) ?? null)
const html = computed(() => (selected.value ? renderMarkdown(selected.value.body) : ''))

onMounted(async () => {
  await Promise.all([store.fetchNotes(1), store.fetchClientOptions().catch(() => undefined)])
  selectedId.value ??= store.items[0]?.id ?? null
})

let timer: ReturnType<typeof setTimeout> | undefined
watch(() => store.search, () => {
  clearTimeout(timer)
  timer = setTimeout(() => store.fetchNotes(1), 300)
})
watch(() => store.scope, () => store.fetchNotes(1))
onBeforeUnmount(() => clearTimeout(timer))

const scopeOptions = computed(() => [
  { value: 'all', label: 'Todas las notas' },
  { value: 'general', label: 'Generales' },
  ...store.clientOptions.map((c) => ({ value: c.id, label: c.display_name })),
])

function onSaved(note: Note) {
  selectedId.value = note.id
}

const formatDate = (iso: string) => new Intl.DateTimeFormat('es-AR', { dateStyle: 'medium' }).format(new Date(iso))
const snippet = (body: string) => body.replace(/[#>*_`\-[\]()]/g, '').replace(/\s+/g, ' ').trim().slice(0, 90)
const scopeLabel = (n: Note) => (n.client_name ? [n.client_name, n.project_name].filter(Boolean).join(' · ') : 'General')

const toDelete = ref<Note | null>(null)
const deleting = ref(false)
const deleteError = ref('')

async function confirmDelete() {
  if (!toDelete.value) return
  deleting.value = true
  deleteError.value = ''
  try {
    await store.deleteNote(toDelete.value.id)
    toDelete.value = null
    selectedId.value = store.items[0]?.id ?? null
  } catch {
    deleteError.value = 'No se pudo eliminar la nota.'
  } finally {
    deleting.value = false
  }
}
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-wrap items-center gap-3">
      <label class="w-full sm:w-72">
        <span class="sr-only">Buscar notas</span>
        <InputText v-model="store.search" placeholder="Buscar en título y contenido" fluid />
      </label>

      <Select v-model="store.scope" :options="scopeOptions" option-label="label" option-value="value" filter class="w-full sm:w-64" aria-label="Filtrar por cliente" />

      <Button class="sm:ml-auto" label="Nueva nota" @click="store.openDialog()" />
    </div>

    <p v-if="store.loadError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ store.loadError }}</p>

    <EmptyState
      v-else-if="!store.loading && store.items.length === 0"
      :title="store.search || store.scope !== 'all' ? 'No hay notas que coincidan' : 'Todavía no escribiste notas'"
      description="Guardá acá tus procesos, plantillas, acuerdos con clientes o lo que quieras consultar después. Se escriben en Markdown."
    />

    <div v-else class="grid gap-8 lg:grid-cols-[20rem_1fr]">
      <div>
        <ul class="divide-y divide-app-border border-y border-app-border">
          <li v-for="note in store.items" :key="note.id">
            <button
              type="button"
              class="block w-full cursor-pointer px-2 py-3 text-left transition-colors hover:bg-app-sunken"
              :class="note.id === selectedId ? 'border-l-2 border-app-accent bg-app-sunken' : 'border-l-2 border-transparent'"
              :aria-current="note.id === selectedId ? 'true' : undefined"
              @click="selectedId = note.id"
            >
              <span class="block font-semibold">{{ note.title }}</span>
              <span class="block text-xs text-app-accent">{{ scopeLabel(note) }}</span>
              <span class="mt-0.5 block text-xs text-app-muted">{{ snippet(note.body) }}</span>
            </button>
          </li>
        </ul>

        <div v-if="store.meta && store.meta.current_page < store.meta.last_page" class="pt-3 text-center">
          <Button label="Cargar más" severity="secondary" text :loading="store.loading" @click="store.loadMore()" />
        </div>
      </div>

      <article v-if="selected" class="min-w-0">
        <header class="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-app-border pb-4">
          <div>
            <h2 class="font-display text-3xl font-medium leading-tight">{{ selected.title }}</h2>
            <p class="mt-1 text-xs text-app-muted">{{ scopeLabel(selected) }} · actualizada el {{ formatDate(selected.updated_at) }}</p>
          </div>
          <div class="flex gap-3 text-xs">
            <button type="button" class="cursor-pointer underline underline-offset-4 hover:text-app-accent" @click="store.openDialog(selected)">Editar</button>
            <button type="button" class="cursor-pointer text-app-muted underline underline-offset-4 hover:text-app-danger" @click="toDelete = selected">Eliminar</button>
          </div>
        </header>

        <!-- renderMarkdown sanitiza el HTML con DOMPurify -->
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div class="markdown" v-html="html" />
      </article>
    </div>

    <NoteFormDialog @saved="onSaved" />

    <Dialog :visible="toDelete !== null" modal header="Eliminar nota" :draggable="false" :style="{ width: '28rem', maxWidth: '95vw' }" @update:visible="(v: boolean) => { if (!v) toDelete = null }">
      <p class="text-sm">¿Eliminar la nota <strong>{{ toDelete?.title }}</strong>? Se oculta pero se conserva en la base de datos.</p>
      <p v-if="deleteError" class="mt-3 text-sm text-app-danger" role="alert">{{ deleteError }}</p>
      <template #footer>
        <Button label="Cancelar" severity="secondary" text :disabled="deleting" @click="toDelete = null" />
        <Button label="Eliminar" severity="danger" :loading="deleting" @click="confirmDelete" />
      </template>
    </Dialog>
  </div>
</template>
