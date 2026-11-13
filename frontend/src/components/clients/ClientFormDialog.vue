<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import Button from 'primevue/button'
import DatePicker from 'primevue/datepicker'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Select from 'primevue/select'
import Textarea from 'primevue/textarea'

import FormField from '@/components/ui/FormField.vue'
import { api, getValidationErrors, type FieldErrors } from '@/lib/api'
import { useClientsStore } from '@/stores/clients'
import { commercialStatusLabels, type Client, serviceTypeLabels, type ClientDataPayload, type CommercialStatus, type NewClientPayload, type ServiceType } from '@/types/client'
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

const commercialOptions = (Object.keys(commercialStatusLabels) as CommercialStatus[]).map((value) => ({
  value,
  label: commercialStatusLabels[value],
}))

interface GeoOption {
  id: number
  name: string
}

const countries = ref<GeoOption[]>([])
const provinces = ref<GeoOption[]>([])
const departments = ref<GeoOption[]>([])

async function loadRegions(countryId: number, parentId?: number): Promise<GeoOption[]> {
  const { data } = await api.get('/geo/regions', { params: { country_id: countryId, parent_id: parentId } })
  return data.data
}

async function onCountryChange(countryId: number | null) {
  form.province_id = null
  form.department_id = null
  provinces.value = []
  departments.value = []
  if (countryId) provinces.value = await loadRegions(countryId)
}

async function onProvinceChange(provinceId: number | null) {
  form.department_id = null
  departments.value = []
  if (provinceId && form.country_id) departments.value = await loadRegions(form.country_id, provinceId)
}

/** Carga las listas y, si se edita un cliente, deja elegido su lugar. */
async function setupLocation(client: Client | null) {
  try {
    const { data } = await api.get('/geo/countries')
    countries.value = data.data

    // Con un solo país cargado se elige solo.
    const countryId = client?.country_id ?? (countries.value.length === 1 ? (countries.value[0]?.id ?? null) : null)
    form.country_id = countryId
    if (!countryId) return

    provinces.value = await loadRegions(countryId)

    if (client?.region_id) {
      form.province_id = client.province_id ?? client.region_id
      if (client.province_id && client.province_id !== client.region_id) {
        departments.value = await loadRegions(countryId, client.province_id)
        form.department_id = client.region_id
      }
    }
  } catch {
    // Sin ubicación se puede guardar igual; la lista queda vacía.
  }
}

function emptyForm() {
  return {
    country_id: null as number | null,
    province_id: null as number | null,
    department_id: null as number | null,
    commercial_status: 'new' as CommercialStatus,
    first_name: '',
    last_name: '',
    company_name: '',
    phone: '',
    whatsapp: '',
    email: '',
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
      const client = store.editing
      void setupLocation(client)
      if (client) {
        Object.assign(form, {
          first_name: client.first_name,
          last_name: client.last_name,
          company_name: client.company_name ?? '',
          phone: client.phone ?? '',
          whatsapp: client.whatsapp ?? '',
          email: client.email ?? '',
          notes: client.notes ?? '',
          commercial_status: client.commercial_status,
        })
      }
      errors.value = {}
      generalError.value = ''
    }
  },
)

const isEditing = computed(() => store.editing !== null)

const firstError = (field: string) => errors.value[field]?.[0]

const clean = (value: string) => value.trim() || undefined
const iso = (value: Date | null) => (value ? toISODate(value) : undefined)

function buildPayload(): NewClientPayload {
  const company = clean(form.company_name)

  const payload: NewClientPayload = {
    first_name: form.first_name.trim(),
    last_name: form.last_name.trim(),
    company_name: company,
    // Si tiene negocio se guarda como empresa; si no, como persona.
    client_type: company ? 'company' : 'person',
    phone: clean(form.phone),
    whatsapp: clean(form.whatsapp),
    email: clean(form.email),
    notes: clean(form.notes),
    // Al crear lo decide el sistema (nuevo → activo cuando arranca un proyecto).
    commercial_status: isEditing.value ? form.commercial_status : undefined,
    country_id: form.country_id,
    region_id: form.department_id ?? form.province_id,
  }

  if (!isEditing.value && form.withProject && form.project.service_type) {
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

/** Al editar, un campo vaciado debe borrarse (null), no quedar como estaba. */
function withClearedFields(data: ClientDataPayload): ClientDataPayload {
  return {
    ...data,
    company_name: data.company_name ?? null,
    phone: data.phone ?? null,
    whatsapp: data.whatsapp ?? null,
    email: data.email ?? null,
    notes: data.notes ?? null,
    country_id: data.country_id ?? null,
    region_id: data.region_id ?? null,
  }
}

async function submit() {
  errors.value = {}
  generalError.value = ''

  // Validación mínima en el navegador; la definitiva la hace el backend.
  const missing: FieldErrors = {}
  if (!form.first_name.trim()) missing.first_name = ['El nombre es obligatorio.']
  if (!form.last_name.trim()) missing.last_name = ['El apellido es obligatorio.']
  if (!isEditing.value && form.withProject && !form.project.service_type) {
    missing['project.service_type'] = ['Elegí el tipo de servicio.']
  }
  if (Object.keys(missing).length > 0) {
    errors.value = missing
    return
  }

  saving.value = true

  try {
    if (store.editing) {
      const { project: _project, ...data } = buildPayload()
      await store.updateClient(store.editing.id, withClearedFields(data))
    } else {
      await store.createClient(buildPayload())
    }
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
    :header="isEditing ? 'Editar cliente' : 'Nuevo cliente'"
    :draggable="false"
    :style="{ width: '42rem', maxWidth: '95vw' }"
  >
    <form id="client-form" class="space-y-6" novalidate @submit.prevent="submit">
      <p v-if="generalError" class="border-l-2 border-app-danger bg-app-accent-soft px-3 py-2 text-sm text-app-danger" role="alert">
        {{ generalError }}
      </p>

      <fieldset class="grid gap-4 sm:grid-cols-2">
        <legend class="sr-only">Datos del cliente</legend>

        <FormField label="Nombre" for="client-first-name" :error="firstError('first_name')" required>
          <InputText id="client-first-name" v-model="form.first_name" autofocus fluid :invalid="!!firstError('first_name')" />
        </FormField>

        <FormField label="Apellido" for="client-last-name" :error="firstError('last_name')" required>
          <InputText id="client-last-name" v-model="form.last_name" fluid :invalid="!!firstError('last_name')" />
        </FormField>

        <div class="sm:col-span-2">
          <FormField label="Empresa o negocio" for="client-company" hint="Opcional. Si lo completás, el cliente se muestra con este nombre." :error="firstError('company_name')">
            <InputText id="client-company" v-model="form.company_name" fluid />
          </FormField>
        </div>

        <FormField label="Teléfono" for="client-phone" :error="firstError('phone')">
          <InputText id="client-phone" v-model="form.phone" type="tel" fluid />
        </FormField>

        <FormField label="WhatsApp" for="client-whatsapp" :error="firstError('whatsapp')">
          <InputText id="client-whatsapp" v-model="form.whatsapp" type="tel" fluid />
        </FormField>

        <div class="sm:col-span-2">
          <FormField label="Correo" for="client-email" :error="firstError('email')">
            <InputText id="client-email" v-model="form.email" type="email" fluid :invalid="!!firstError('email')" />
          </FormField>
        </div>

        <FormField label="País" for="client-country" :error="firstError('country_id')">
          <Select v-model="form.country_id" input-id="client-country" :options="countries" option-label="name" option-value="id" placeholder="Sin especificar" show-clear fluid @update:model-value="onCountryChange" />
        </FormField>

        <FormField label="Provincia" for="client-province" :error="firstError('region_id')">
          <Select v-model="form.province_id" input-id="client-province" :options="provinces" option-label="name" option-value="id" placeholder="Sin especificar" show-clear filter :disabled="!form.country_id" fluid @update:model-value="onProvinceChange" />
        </FormField>

        <div class="sm:col-span-2">
          <FormField label="Departamento" for="client-department" hint="Se muestra después de elegir la provincia.">
            <Select v-model="form.department_id" input-id="client-department" :options="departments" option-label="name" option-value="id" placeholder="Sin especificar" show-clear filter :disabled="departments.length === 0" fluid />
          </FormField>
        </div>

        <div v-if="isEditing" class="sm:col-span-2">
          <FormField label="Estado del cliente" for="client-status" :error="firstError('commercial_status')">
            <Select v-model="form.commercial_status" input-id="client-status" :options="commercialOptions" option-label="label" option-value="value" fluid />
          </FormField>
        </div>

        <div class="sm:col-span-2">
          <FormField label="Notas" for="client-notes" :error="firstError('notes')">
            <Textarea id="client-notes" v-model="form.notes" rows="2" auto-resize fluid />
          </FormField>
        </div>
      </fieldset>

      <fieldset v-if="!isEditing" class="space-y-4 border-t border-app-border pt-5">
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
      <Button type="submit" form="client-form" :label="isEditing ? 'Guardar cambios' : 'Guardar cliente'" :loading="saving" />
    </template>
  </Dialog>
</template>
