export type AgendaEventType = 'delivery' | 'start' | 'payment' | 'reminder'

export interface AgendaEvent {
  key: string
  type: AgendaEventType
  id: number
  /** YYYY-MM-DD */
  date: string
  title: string
  client: string | null
  amount: number | null
  notes?: string | null
  client_id?: number | null
}

export interface Agenda {
  overdue: AgendaEvent[]
  upcoming: AgendaEvent[]
}

export interface AgendaSummary {
  overdue: number
  today: number
}

export interface ReminderPayload {
  client_id: number | null
  title: string
  notes: string | null
  remind_on: string
  done: boolean
}
