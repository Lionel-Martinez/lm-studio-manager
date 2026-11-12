<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

import FormField from '@/components/ui/FormField.vue'
import { getValidationErrors, type FieldErrors } from '@/lib/api'
import { useClientsStore } from '@/stores/clients'
import { serviceTypeLabels, type NewClientPayload, type ServiceType } from '@/types/client'
import { projectStatusLabels, type ProjectStatus } from '@/types/dashboard'
import { toISODate } from '@/utils/dates'

const store = useClientsStore()

const serviceOptions = (Object.keys(serviceTypeLabels) as ServiceType[]).map((value) => ({
  value,
  label: serviceTypeLabels[value],
}))

const statusOptions = (Object.keys(projectStatusLabels) as ProjectStatus[]).map((value) => ({
  value,
  label: projectStatusLabels[value],
}))

function emptyForm() {
  return {
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    city: '',
    notes: '',
    withProject: true,
    project: {
      service_type: null as ServiceType | null,
      name: '',
      status: 'prospect' as ProjectStatus,
      entry_date: new Date() as Date | null,
      estimated_start_date: null as Date | null,
      due_date: null as Date | null,
      delay_reason: '',
    },
  }
}

const form = reactive(emptyForm())
const errors = ref<FieldErrors>({})
const generalError = ref('')
const saving = ref(false)

const visible = computed({
  get: () => store.dialogOpen,
  set: (value: boolean) => (value ? store.openDialog() : store.closeDialog()),
})

// Cada vez que se abre el diálogo se parte de un formulario limpio.
watch(
  () => store.dialogOpen,
  (open) => {
    if (open) {
      Object.assign(form, emptyForm())
      errors.value = {}
      generalError.value = ''
    }
  },
)

const firstError = (field: string) => errors.value[field]?.[0]

const clean = (value: string) => value.trim() || undefined
const iso = (value: Date | null) => (value ? toISODate(value) : undefined)

function buildPayload(): NewClientPayload {
  const payload: NewClientPayload = {
    name: form.name.trim(),
    contact_name: clean(form.contact_name),
    phone: clean(form.phone),
    email: clean(form.email),
    city: clean(form.city),
    notes: clean(form.notes),
  }

  if (form.withProject && form.project.service_type) {
    payload.project = {
      service_type: form.project.service_type,
      name: clean(form.project.name),
      status: form.project.status,
      entry_date: iso(form.project.entry_date),
      estimated_start_date: iso(form.project.estimated_start_date),
      due_date: iso(form.project.due_date),
      delay_reason: clean(form.project.delay_reason),
    }
  }

  return payload
}

async function submit() {
  errors.value = {}
  generalError.value = ''

  // Validación mínima en el navegador; la definitiva la hace el backend.
  if (!form.name.trim()) {
    errors.value = { name: ['El nombre es obligatorio.'] }
    return
  }
  if (form.withProject && !form.project.service_type) {
    errors.value = { 'project.service_type': ['Elegí el tipo de servicio.'] }
    return
  }

  saving.value = true

  try {
    await store.createClient(buildPayload())
    store.closeDialog()
  } catch (error) {
    const validation = getValidationErrors(error)

    if (validation) {
      errors.value = validation
    } else {
      generalError.value = 'No se pudo guardar el cliente. Verificá que el backend esté en marcha e intentá de nuevo.'
    }
  } finally {
    saving.value = false
  }
}
</script>

<template>
  <Dialog
    v-model:visible="visible"
    modal
    header="Nuevo cliente"
    :draggable="false"
    :style="{ width: '42rem', maxWidth: '95vw' }"
  >
    <form id="client-form" class="space-y-6" novalidate @submit.prevent="submit">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
        {{ generalError }}
      </p>

      <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="sr-only">Datos del cliente</legend>

        <div class="sm:col-span-2">
          <FormField label="Nombre o negocio" for="client-name" :error="firstError('name')" required>
            <InputText id="client-name" v-model="form.name" autofocus fluid :invalid="!!firstError('name')" />
          </FormField>
        </div>

        <FormField label="Persona de contacto" for="client-contact" :error="firstError('contact_name')">
          <InputText id="client-contact" v-model="form.contact_name" fluid />
        </FormField>

        <FormField label="Teléfono" for="client-phone" :error="firstError('phone')">
          <InputText id="client-phone" v-model="form.phone" type="tel" fluid />
        </FormField>

        <FormField label="Correo" for="client-email" :error="firstError('email')">
          <InputText id="client-email" v-model="form.email" type="email" fluid :invalid="!!firstError('email')" />
        </FormField>

        <FormField label="Localidad" for="client-city" :error="firstError('city')">
          <InputText id="client-city" v-model="form.city" fluid />
        </FormField>

        <div class="sm:col-span-2">
          <FormField label="Notas" for="client-notes" :error="firstError('notes')">
            <Textarea id="client-notes" v-model="form.notes" rows="2" auto-resize fluid />
          </FormField>
        </div>
      </fieldset>

      <fieldset class="space-y-4 border-t border-app-border pt-5">
        <legend class="sr-only">Primer proyecto</legend>

        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold">
          <input v-model="form.withProject" type="checkbox" class="size-4 accent-[var(--app-accent)]" />
          Registrar también su primer proyecto
        </label>

        <div v-if="form.withProject" class="grid gap-4 sm:grid-cols-2">
          <FormField label="Servicio" for="project-service" :error="firstError('project.service_type')" required>
            <Select
              v-model="form.project.service_type"
              input-id="project-service"
              :options="serviceOptions"
              option-label="label"
              option-value="value"
              placeholder="Elegí un servicio"
              fluid
              :invalid="!!firstError('project.service_type')"
            />
          </FormField>

          <FormField label="Estado" for="project-status" :error="firstError('project.status')">
            <Select
              v-model="form.project.status"
              input-id="project-status"
              :options="statusOptions"
              option-label="label"
              option-value="value"
              fluid
            />
          </FormField>

          <div class="sm:col-span-2">
            <FormField label="Nombre del proyecto" for="project-name" hint="Opcional. Por ejemplo: Menú QR del local." :error="firstError('project.name')">
              <InputText id="project-name" v-model="form.project.name" fluid />
            </FormField>
          </div>

          <FormField label="Fecha de ingreso" for="project-entry" :error="firstError('project.entry_date')">
            <DatePicker v-model="form.project.entry_date" input-id="project-entry" date-format="dd/mm/yy" show-icon fluid />
          </FormField>

          <FormField label="Inicio estimado" for="project-start" hint="Dejalo vacío si todavía no lo sabés." :error="firstError('project.estimated_start_date')">
            <DatePicker v-model="form.project.estimated_start_date" input-id="project-start" date-format="dd/mm/yy" show-icon show-button-bar fluid />
          </FormField>

          <FormField label="Entrega estimada" for="project-due" :error="firstError('project.due_date')">
            <DatePicker
              v-model="form.project.due_date"
              input-id="project-due"
              date-format="dd/mm/yy"
              :min-date="form.project.estimated_start_date ?? undefined"
              show-icon
              show-button-bar
              fluid
              :invalid="!!firstError('project.due_date')"
            />
          </FormField>

          <FormField label="Motivo de la demora" for="project-delay" hint="Si vas a tardar en empezar o entregar, anotá por qué." :error="firstError('project.delay_reason')">
            <InputText id="project-delay" v-model="form.project.delay_reason" fluid />
          </FormField>
        </div>
      </fieldset>
    </form>

    <template #footer>
      <Button label="Cancelar" severity="secondary" text :disabled="saving" @click="store.closeDialog()" />
      <Button type="submit" form="client-form" label="Guardar cliente" :loading="saving" />
    </template>
  </Dialog>
</template>
