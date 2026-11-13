import { ref } from 'vue'

import { api } from '@/lib/api'

export type ExportKind = 'clients' | 'projects' | 'payments'

const filenames: Record<ExportKind, string> = {
  clients: 'clientes',
  projects: 'proyectos',
  payments: 'pagos',
}

/** Descarga un CSV desde la API (con la sesión) y lo guarda como archivo. */
export function useCsvDownload() {
  const downloading = ref<ExportKind | null>(null)
  const error = ref('')

  async function download(kind: ExportKind) {
    downloading.value = kind
    error.value = ''

    try {
      const { data } = await api.get(`/export/${kind}`, { responseType: 'blob' })
      const url = URL.createObjectURL(data as Blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `${filenames[kind]}-${new Date().toISOString().slice(0, 10)}.csv`
      document.body.appendChild(link)
      link.click()
      link.remove()
      URL.revokeObjectURL(url)
    } catch {
      error.value = 'No se pudo descargar el archivo. Intentá de nuevo.'
    } finally {
      downloading.value = null
    }
  }

  return { downloading, error, download }
}
