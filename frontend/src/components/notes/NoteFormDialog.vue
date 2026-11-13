<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { renderMarkdown } from '@/lib/markdown'
import { useNotesStore } from '@/stores/notes'
import type { Note } from '@/types/note'

const emit = defineEmits<{ saved: [note: Note] }>()

const store = useNotesStore()

const draft = reactive({ client_id: null as number | null, project_id: null as number | null, title: '', body: '' })
const errors = ref<FieldErrors>({})
const generalError = ref('')
const saving = ref(false)
const preview = ref(false)

const isEditing = computed(() => store.editing !== null)
const visible = computed({
  get: () => store.dialogOpen,
  set: (value: boolean) => {
    if (!value) store.closeDialog()
  },
})

const clientChoices = computed(() => store.clientOptions.map((c) => ({ value: c.id, label: c.display_name })))
const projectChoices = computed(() => {
  const client = store.clientOptions.find((c) => c.id === draft.client_id)
  return (client?.projects ?? []).map((p) => ({ value: p.id, label: p.name || p.service_type_label }))
})
const html = computed(() => renderMarkdown(draft.body))

watch(
  () => store.dialogOpen,
  async (open) => {
    if (!open) return
    preview.value = false
    errors.value = {}
    generalError.value = ''

    const n = store.editing
    Object.assign(draft, {
      client_id: n?.client_id ?? (typeof store.scope === 'number' ? store.scope : null),
      project_id: n?.project_id ?? null,
      title: n?.title ?? '',
      body: n?.body ?? '',
    })

    try {
      await store.fetchClientOptions()
    } catch {
      generalError.value = 'No se pudo cargar la lista de clientes.'
    }
  },
)

// Sin cliente no puede haber proyecto; si cambia el cliente, el proyecto ya no corresponde.
watch(() => draft.client_id, () => {
  draft.project_id = null
})

function close() {
  store.closeDialog()
}

const firstError = (field: string) => errors.value[field]?.[0]

async function submit() {
  errors.value = {}
  generalError.value = ''

  const missing: FieldErrors = {}
  if (!draft.title.trim()) missing.title = ['El título es obligatorio.']
  if (!draft.body.trim()) missing.body = ['Escribí el contenido de la nota.']
  if (Object.keys(missing).length) {
    errors.value = missing
    return
  }

  saving.value = true

  try {
    const note = await store.saveNote({ client_id: draft.client_id, project_id: draft.project_id, title: draft.title.trim(), body: draft.body })
    store.closeDialog()
    emit('saved', note)
  } catch (error) {
    const validation = getValidationErrors(error)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo guardar la nota. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="isEditing ? 'Editar nota' : 'Nueva nota'" :draggable="false" :style="{ width: '46rem', maxWidth: '95vw' }">
    <form id="note-form" class="space-y-4" novalidate @submit.prevent="submit">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ generalError }}</p>

      <FormField label="Título" for="note-title" :error="firstError('title')" required>
        <InputText id="note-title" v-model="draft.title" fluid :invalid="!!firstError('title')" />
      </FormField>

      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Cliente" for="note-client" hint="Vacío = nota general." :error="firstError('client_id')">
          <Select v-model="draft.client_id" input-id="note-client" :options="clientChoices" option-label="label" option-value="value" placeholder="Nota general" show-clear filter fluid />
        </FormField>

        <FormField label="Proyecto" for="note-project" :error="firstError('project_id')">
          <Select v-model="draft.project_id" input-id="note-project" :options="projectChoices" option-label="label" option-value="value" placeholder="Sin proyecto" show-clear :disabled="!draft.client_id" fluid />
        </FormField>
      </div>

      <div>
        <div class="mb-1.5 flex items-center justify-between">
          <label for="note-body" class="text-sm font-medium">Contenido <span class="text-app-danger" aria-hidden="true">*</span></label>
          <div class="flex gap-3 text-xs" role="group" aria-label="Modo">
            <button type="button" class="cursor-pointer underline-offset-4" :class="!preview ? 'font-semibold underline' : 'text-app-muted'" @click="preview = false">Escribir</button>
            <button type="button" class="cursor-pointer underline-offset-4" :class="preview ? 'font-semibold underline' : 'text-app-muted'" @click="preview = true">Vista previa</button>
          </div>
        </div>

        <Textarea v-if="!preview" id="note-body" v-model="draft.body" rows="12" fluid :invalid="!!firstError('body')" placeholder="Escribí en Markdown: # Título, **negrita**, - listas, [enlace](https://…)" />
        <!-- eslint-disable-next-line vue/no-v-html -->
        <div v-if="preview" class="markdown min-h-[18rem] border border-app-border bg-app-surface p-4" v-html="html" />
        <p v-if="firstError('body')" class="mt-1.5 text-xs text-app-danger" role="alert">{{ firstError('body') }}</p>
      </div>
    </form>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="saving" @click="close" />
      <Button type="submit" form="note-form" :label="isEditing ? 'Guardar cambios' : 'Guardar nota'" :loading="saving" />
    </template>
  </Dialog>
</template>
