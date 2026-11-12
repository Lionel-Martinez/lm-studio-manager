/** Etapa de un proyecto en el flujo comercial. */
export type ProjectStatus = 'prospect' | 'waiting' | 'in_progress' | 'ready' | 'delivered'

/** Proyecto tal como se muestra en el calendario de entregas y en la lista de espera. */
export interface DeliveryItem {
  id: number
  client: string
  service: string
  status: ProjectStatus
  /** Fecha en que el cliente se registró (YYYY-MM-DD). */
  entryDate: string
  /** Inicio estimado o real. `null` si todavía no está definido. */
  startDate: string | null
  /** Entrega estimada. `null` si todavía no está definida. */
  dueDate: string | null
  /** Motivo por el que el proyecto se va a demorar, si corresponde. */
  delayReason?: string
}

/** Cuota o pago que todavía no se acreditó. */
export interface PendingPayment {
  id: number
  client: string
  concept: string
  /** Fecha de vencimiento (YYYY-MM-DD). */
  dueDate: string
  amount: number
}

export interface RevenuePoint {
  label: string
  value: number
}

export interface ClientDistributionPoint {
  label: string
  value: number
}

export const projectStatusLabels: Record<ProjectStatus, string> = {
  prospect: 'Prospecto',
  waiting: 'En espera',
  in_progress: 'En desarrollo',
  ready: 'Por entregar',
  delivered: 'Entregado',
}
