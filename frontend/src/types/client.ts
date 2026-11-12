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
  commercial_status: string
  first_contact_at: string | null
  source: string | null
  notes: string | null
  country_id: number | null
  region_id: number | null
  locality_id: number | null
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
