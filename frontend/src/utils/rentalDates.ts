export const RENTAL_TZ = 'Asia/Jakarta'

export const RENTAL_START_MESSAGE =
  'Tanggal mulai sewa minimal besok karena pemesanan wajib dilakukan H-1.'

export const RENTAL_END_MESSAGE =
  'Tanggal selesai sewa tidak boleh mendahului tanggal mulai sewa.'

/** Today's date (YYYY-MM-DD) in Asia/Jakarta, independent of browser timezone. */
export const jakartaToday = (): string =>
  new Intl.DateTimeFormat('en-CA', { timeZone: RENTAL_TZ }).format(new Date())

/** Earliest selectable rental start date (tomorrow in Asia/Jakarta). */
export const minRentalStartDate = (): string => {
  const [year, month, day] = jakartaToday().split('-').map(Number)
  const next = new Date(Date.UTC(year, month - 1, day + 1))
  return new Intl.DateTimeFormat('en-CA', { timeZone: 'UTC' }).format(next)
}

export const isRentalStartValid = (startDate: string): boolean =>
  Boolean(startDate) && startDate >= minRentalStartDate()

export const isRentalEndValid = (startDate: string, endDate: string): boolean =>
  Boolean(startDate) && Boolean(endDate) && endDate >= startDate
