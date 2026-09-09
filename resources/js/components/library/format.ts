const dateFormatter = new Intl.DateTimeFormat('es-MX', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

export function formatBytes(bytes: number | null): string {
  if (!bytes)
    return 'Sin tamaño'
  const units = ['B', 'KB', 'MB']
  let value = bytes
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }
  return `${value.toFixed(unit ? 1 : 0)} ${units[unit]}`
}

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

export const statusLabel: Record<string, string> = {
  pending: 'Pendiente',
  in_progress: 'Procesando',
  completed: 'Indexado',
  failed: 'Falló',
}
