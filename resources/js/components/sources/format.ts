export { formatBytes } from '@/lib/files'

const dateFormatter = new Intl.DateTimeFormat('es-MX', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

export function formatDate(date: string | null | undefined): string {
  return date ? dateFormatter.format(new Date(date)) : 'Sin fecha'
}

/** Las fechas del reporte de OpenAI vienen en segundos, no en milisegundos. */
export function formatTimestamp(seconds: number): string {
  return seconds ? dateFormatter.format(new Date(seconds * 1000)) : 'Sin fecha'
}

export function shortId(id: string): string {
  return id.length > 14 ? `${id.slice(0, 11)}…` : id
}

export const originLabel: Record<string, string> = {
  pentaho: 'Pentaho',
  manual: 'Manual',
}

export const statusLabel: Record<string, string> = {
  pending: 'Pendiente',
  in_progress: 'Procesando',
  completed: 'Indexado',
  failed: 'Falló',
}
