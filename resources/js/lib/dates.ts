const shortDate = new Intl.DateTimeFormat('es-MX', {
  day: 'numeric',
  month: 'short',
})

const longDate = new Intl.DateTimeFormat('es-MX', {
  day: 'numeric',
  month: 'long',
  year: 'numeric',
})

function parse(value: string | null | undefined): Date | null {
  if (!value)
    return null

  const date = new Date(value)

  return Number.isNaN(date.getTime()) ? null : date
}

/** «14 sep» para chips y listas donde el año sobra. */
export function formatShortDate(value: string | null | undefined): string | null {
  const date = parse(value)

  return date ? shortDate.format(date) : null
}

/** «14 de septiembre de 2026» para encabezados y detalles. */
export function formatLongDate(value: string | null | undefined): string | null {
  const date = parse(value)

  return date ? longDate.format(date) : null
}

export function daysSince(value: string | null | undefined): number | null {
  const date = parse(value)

  if (!date)
    return null

  return Math.floor((Date.now() - date.getTime()) / 86_400_000)
}

const time = new Intl.DateTimeFormat('es-MX', {
  hour: '2-digit',
  minute: '2-digit',
  hour12: false,
})

/** «12:37», sin segundos ni am/pm. */
export function formatTime(value: string | null | undefined): string | null {
  const date = parse(value)

  return date ? time.format(date) : null
}

/** «hoy», «ayer» o «2 sep» según qué tan reciente sea la fecha. */
export function formatRelativeDayName(value: string | null | undefined): string | null {
  const date = parse(value)

  if (!date)
    return null

  const days = calendarDaysAgo(date)

  if (days === 0)
    return 'hoy'

  if (days === 1)
    return 'ayer'

  return shortDate.format(date)
}

/** «hoy, 11:42» para el día en curso; «ayer» o «2 sep» para el resto. */
export function formatRelativeDay(value: string | null | undefined): string | null {
  const name = formatRelativeDayName(value)

  if (!name)
    return null

  return name === 'hoy' ? `hoy, ${formatTime(value)}` : name
}

/**
 * Días de calendario transcurridos, no de 24 horas: algo de las 23:50 de ayer
 * es «ayer» aunque hayan pasado diez minutos.
 */
function calendarDaysAgo(date: Date): number {
  const startOfToday = new Date()
  startOfToday.setHours(0, 0, 0, 0)

  const startOfDate = new Date(date)
  startOfDate.setHours(0, 0, 0, 0)

  return Math.round((startOfToday.getTime() - startOfDate.getTime()) / 86_400_000)
}
