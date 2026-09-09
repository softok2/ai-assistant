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
