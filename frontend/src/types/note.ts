export interface Note {
  id: number
  client_id: number | null
  client_name: string | null
  project_id: number | null
  project_name: string | null
  title: string
  /** Contenido en Markdown. */
  body: string
  updated_at: string
}

export interface NotePayload {
  client_id: number | null
  project_id: number | null
  title: string
  body: string
}
