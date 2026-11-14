<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { useAgendaStore } from '@/stores/agenda'

const store = useAgendaStore()

const draft = reactive({ client_id: null as number | null, title: '', notes: '', remind_on: '' })
const errors = ref<FieldErrors>({})
const generalError = ref('')
const saving = ref(false)

const isEditing = computed(() => store.editing !== null)
const visible = computed({
  get: () => store.dialogOpen,
  set: (value: boolean) => {
    if (!value) store.closeDialog()
  },
})
const clientChoices = computed(() => store.clientOptions.map((c) => ({ value: c.id, label: c.display_name })))

watch(
  () => store.dialogOpen,
  async (open) => {
    if (!open) return
    errors.value = {}
    generalError.value = ''
    const e = store.editing
    Object.assign(draft, {
      client_id: e?.client_id ?? null,
      title: e?.title ?? '',
      notes: e?.notes ?? '',
      remind_on: e?.date ?? new Date().toLocaleDateString('en-CA'),
    })
    try {
      await store.fetchClientOptions()
    } catch {
      generalError.value = 'No se pudo cargar la lista de clientes.'
    }
  },
)

const firstError = (field: string) => errors.value[field]?.[0]

async function submit() {
  errors.value = {}
  generalError.value = ''

  const missing: FieldErrors = {}
  if (!draft.title.trim()) missing.title = ['El título es obligatorio.']
  if (!draft.remind_on) missing.remind_on = ['Elegí una fecha.']
  if (Object.keys(missing).length) {
    errors.value = missing
    return
  }

  saving.value = true
  try {
    await store.saveReminder({
      client_id: draft.client_id,
      title: draft.title.trim(),
      notes: draft.notes.trim() || null,
      remind_on: draft.remind_on,
      done: false,
    })
    store.closeDialog()
  } catch (error) {
    const validation = getValidationErrors(error)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo guardar el recordatorio. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="isEditing ? 'Editar recordatorio' : 'Nuevo recordatorio'" :draggable="false" :style="{ width: '32rem', maxWidth: '95vw' }">
    <form id="reminder-form" class="space-y-4" novalidate @submit.prevent="submit">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ generalError }}</p>

      <FormField label="Qué hay que hacer" for="rem-title" :error="firstError('title')" required>
        <InputText id="rem-title" v-model="draft.title" fluid maxlength="150" :invalid="!!firstError('title')" />
      </FormField>

      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Fecha" for="rem-date" :error="firstError('remind_on')" required>
          <InputText id="rem-date" v-model="draft.remind_on" type="date" fluid :invalid="!!firstError('remind_on')" />
        </FormField>
        <FormField label="Cliente" for="rem-client" :error="firstError('client_id')">
          <Select v-model="draft.client_id" input-id="rem-client" :options="clientChoices" option-label="label" option-value="value" placeholder="Sin cliente" show-clear filter fluid />
        </FormField>
      </div>

      <FormField label="Notas" for="rem-notes" :error="firstError('notes')">
        <Textarea id="rem-notes" v-model="draft.notes" rows="3" maxlength="500" fluid />
      </FormField>
    </form>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="saving" @click="store.closeDialog()" />
      <Button type="submit" form="reminder-form" :label="isEditing ? 'Guardar cambios' : 'Guardar recordatorio'" :loading="saving" />
    </template>
  </Dialog>
</template>
