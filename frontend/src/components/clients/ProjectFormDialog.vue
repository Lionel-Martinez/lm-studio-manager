<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { useClientsStore } from '@/stores/clients'
import { serviceTypeLabels, type NewProjectPayload, type ServiceType } from '@/types/client'
import { projectStatusLabels, type ProjectStatus } from '@/types/dashboard'
import { parseISODate, toISODate } from '@/utils/dates'

const store = useClientsStore()

const serviceOptions = (Object.keys(serviceTypeLabels) as ServiceType[]).map((value) => ({ value, label: serviceTypeLabels[value] }))
const statusOptions = (Object.keys(projectStatusLabels) as ProjectStatus[]).map((value) => ({ value, label: projectStatusLabels[value] }))

function emptyForm() {
  return {
    service_type: null as ServiceType | null,
    name: '',
    status: 'prospect' as ProjectStatus,
    entry_date: new Date() as Date | null,
    estimated_start_date: null as Date | null,
    due_date: null as Date | null,
    delay_reason: '',
  }
}

const form = reactive(emptyForm())
const errors = ref<FieldErrors>({})
const generalError = ref('')
const saving = ref(false)

const dialog = computed(() => store.projectDialog)
const visible = computed({
  get: () => dialog.value !== null,
  set: (value: boolean) => {
    if (!value) store.closeProjectDialog()
  },
})
const isEditing = computed(() => dialog.value?.project != null)

watch(dialog, (current) => {
  if (!current) return
  Object.assign(form, emptyForm())
  errors.value = {}
  generalError.value = ''

  const p = current.project
  if (p) {
    Object.assign(form, {
      service_type: p.service_type,
      name: p.name ?? '',
      status: p.status,
      entry_date: parseISODate(p.entry_date),
      estimated_start_date: p.estimated_start_date ? parseISODate(p.estimated_start_date) : null,
      due_date: p.due_date ? parseISODate(p.due_date) : null,
      delay_reason: p.delay_reason ?? '',
    })
  }
})

const firstError = (field: string) => errors.value[field]?.[0]
const iso = (value: Date | null) => (value ? toISODate(value) : null)

async function submit() {
  errors.value = {}
  generalError.value = ''

  if (!form.service_type) {
    errors.value = { service_type: ['Elegí el tipo de servicio.'] }
    return
  }

  // Al editar, un campo vaciado se manda como null para que se borre.
  const payload = {
    service_type: form.service_type,
    name: form.name.trim() || null,
    status: form.status,
    entry_date: iso(form.entry_date),
    estimated_start_date: iso(form.estimated_start_date),
    due_date: iso(form.due_date),
    delay_reason: form.delay_reason.trim() || null,
  } as unknown as NewProjectPayload

  saving.value = true

  try {
    await store.saveProject(payload)
    store.closeProjectDialog()
  } catch (error) {
    const validation = getValidationErrors(error)
    if (validation) errors.value = validation
    else generalError.value = 'No se pudo guardar el proyecto. Intentá de nuevo.'
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    :header="isEditing ? 'Editar proyecto' : 'Nuevo proyecto'"
    :draggable="false"
    :style="{ width: '38rem', maxWidth: '95vw' }"
  >
    <form id="project-form" class="space-y-4" novalidate @submit.prevent="submit">
      <p class="text-sm text-app-muted">Cliente: <span class="font-semibold text-app-text">{{ dialog?.clientName }}</span></p>

      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">{{ generalError }}</p>

      <div class="grid gap-4 sm:grid-cols-2">
        <FormField label="Servicio" for="pf-service" :error="firstError('service_type')" required>
          <Select v-model="form.service_type" input-id="pf-service" :options="serviceOptions" option-label="label" option-value="value" placeholder="Elegí un servicio" fluid :invalid="!!firstError('service_type')" />
        </FormField>

        <FormField label="Estado" for="pf-status" :error="firstError('status')">
          <Select v-model="form.status" input-id="pf-status" :options="statusOptions" option-label="label" option-value="value" fluid />
        </FormField>

        <div class="sm:col-span-2">
          <FormField label="Nombre del proyecto" for="pf-name" :error="firstError('name')">
            <InputText id="pf-name" v-model="form.name" fluid />
          </FormField>
        </div>

        <FormField label="Fecha de ingreso" for="pf-entry" :error="firstError('entry_date')">
          <DatePicker v-model="form.entry_date" input-id="pf-entry" date-format="dd/mm/yy" show-icon fluid />
        </FormField>

        <FormField label="Inicio estimado" for="pf-start" :error="firstError('estimated_start_date')">
          <DatePicker v-model="form.estimated_start_date" input-id="pf-start" date-format="dd/mm/yy" show-icon show-button-bar fluid />
        </FormField>

        <FormField label="Entrega estimada" for="pf-due" :error="firstError('due_date')">
          <DatePicker v-model="form.due_date" input-id="pf-due" date-format="dd/mm/yy" :min-date="form.estimated_start_date ?? undefined" show-icon show-button-bar fluid :invalid="!!firstError('due_date')" />
        </FormField>

        <FormField label="Motivo de la demora" for="pf-delay" :error="firstError('delay_reason')">
          <InputText id="pf-delay" v-model="form.delay_reason" fluid />
        </FormField>
      </div>
    </form>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="saving" @click="store.closeProjectDialog()" />
      <Button type="submit" form="project-form" :label="isEditing ? 'Guardar cambios' : 'Guardar proyecto'" :loading="saving" />
    </template>
  </Dialog>
</template>
