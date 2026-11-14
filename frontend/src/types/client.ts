import type { ProjectStatus } from '@/types/dashboard'

export type ServiceType = 'mobile_app' | 'web_app' | 'digital_menu' | 'static_site'

export const serviceTypeLabels: Record<ServiceType, string> = {
  mobile_app: 'Aplicación móvil',
  web_app: 'Aplicación web',
  digital_menu: 'Menú digital',
  static_site: 'Página web estática',
}

export interface ClientProject {
  id: number
  client_id: number
  service_type: ServiceType
  service_type_label: string
  name: string | null
  status: ProjectStatus
  status_label: string
  entry_date: string
  estimated_start_date: string | null
  due_date: string | null
  delay_reason: string | null
}

export type ClientType = 'person' | 'company'

export type CommercialStatus = 'new' | 'active' | 'inactive'
export type DeactivationReason = 'non_payment' | 'no_activity' | 'client_request' | 'other'

export const deactivationReasonLabels: Record<DeactivationReason, string> = {
  non_payment: 'Falta de pago',
  no_activity: 'Sin actividad',
  client_request: 'Lo pidió el cliente',
  other: 'Otro motivo',
}

export const commercialStatusLabels: Record<CommercialStatus, string> = {
  new: 'Nuevo',
  active: 'Activo',
  inactive: 'Inactivo',
}

export interface Client {
  id: number
  /** Nombre del negocio si tiene; si no, nombre y apellido de la persona. */
  display_name: string
  first_name: string
  last_name: string
  company_name: string | null
  tax_id: string | null
  email: string | null
  phone: string | null
  whatsapp: string | null
  website: string | null
  client_type: ClientType
  commercial_status: CommercialStatus
  commercial_status_label: string
  deactivated_at: string | null
  deactivation_reason: DeactivationReason | null
  deactivation_reason_label: string | null
  deactivation_note: string | null
  first_contact_at: string | null
  source: string | null
  notes: string | null
  country_id: number | null
  region_id: number | null
  locality_id: number | null
  country_name?: string | null
  region_name?: string | null
  /** Provincia del cliente (si `region_id` es un departamento, es su provincia). */
  province_id?: number | null
  province_name?: string | null
  /** Por ejemplo "Orán, Salta". */
  location_label?: string | null
  created_at: string
  projects: ClientProject[]
}

export interface NewProjectPayload {
  service_type: ServiceType
  name?: string
  status?: ProjectStatus
  entry_date?: string
  estimated_start_date?: string
  due_date?: string
  delay_reason?: string
}

/** Datos propios del cliente (sirven para crear y para editar). */
export interface ClientDataPayload {
  first_name: string
  last_name: string
  company_name?: string | null
  email?: string | null
  phone?: string | null
  whatsapp?: string | null
  client_type?: ClientType
  commercial_status?: CommercialStatus
  country_id?: number | null
  /** Lugar más específico: el departamento si se eligió, si no la provincia. */
  region_id?: number | null
  notes?: string | null
}

export interface NewClientPayload extends ClientDataPayload {
  project?: NewProjectPayload
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  total: number
}
