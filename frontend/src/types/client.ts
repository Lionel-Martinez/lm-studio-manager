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

export interface Client {
  id: number
  name: string
  contact_name: string | null
  email: string | null
  phone: string | null
  city: string | null
  notes: string | null
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

export interface NewClientPayload {
  name: string
  contact_name?: string
  email?: string
  phone?: string
  city?: string
  notes?: string
  project?: NewProjectPayload
}

export interface PaginationMeta {
  current_page: number
  last_page: number
  total: number
}
