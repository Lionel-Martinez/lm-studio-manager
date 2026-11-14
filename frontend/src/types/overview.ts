export interface ServiceOverview {
  type: string
  label: string
  projects: number
  clients: number
  by_status: { status: string; label: string; count: number }[]
  late: number
  collected: number
  pending: number
}

export interface GeoNode {
  id: number
  name: string
  clients: number
  revenue: number
  country_id?: number
  province_id?: number
}

export interface GeographyOverview {
  total_clients: number
  countries: GeoNode[]
  provinces: GeoNode[]
  departments: GeoNode[]
  unlocated: { clients: number; revenue: number }
}
