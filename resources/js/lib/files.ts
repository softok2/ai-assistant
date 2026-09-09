/**
 * Tamaño legible de un archivo. El texto de respaldo cambia según la
 * pantalla: la tabla de fuentes dice «Sin tamaño» y la Biblioteca prefiere no
 * mostrar nada.
 */
export function formatBytes(bytes: number | null | undefined, fallback = 'Sin tamaño'): string {
  if (!bytes)
    return fallback

  const units = ['B', 'KB', 'MB']
  let value = bytes
  let unit = 0

  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit++
  }

  return `${value.toFixed(unit ? 1 : 0)} ${units[unit]}`
}

/** «PDF», «XLSX»; cadena vacía cuando el nombre no trae extensión. */
export function fileExtension(name: string): string {
  const parts = name.split('.')

  return parts.length > 1 ? (parts.pop() as string).toUpperCase() : ''
}
