export interface AuthUser {
  id: number
  name: string
  email: string
}

export interface ActivityEntry {
  id: number
  event: string
  label: string
  ip_address: string | null
  created_at: string
}
