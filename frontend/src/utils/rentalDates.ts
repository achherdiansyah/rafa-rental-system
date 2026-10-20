export const RENTAL_TZ = 'Asia/Jakarta'

export const RENTAL_START_MESSAGE =
  'Tanggal mulai sewa tidak boleh sebelum hari ini.'

export const RENTAL_END_MESSAGE =
  'Tanggal selesai sewa tidak boleh mendahului tanggal mulai sewa.'

/** Today's date (YYYY-MM-DD) in Asia/Jakarta, independent of browser timezone. */
export const jakartaToday = (): string =>
  new Intl.DateTimeFormat('en-CA', { timeZone: RENTAL_TZ }).format(new Date())

/**
 * Earliest selectable rental start date (today in Asia/Jakarta).
 * NOTE: H-1 is temporarily disabled for the thesis demo; add one day here
 * (and restore RENTAL_START_MESSAGE) to re-enable it.
 */
export const minRentalStartDate = (): string => jakartaToday()

export const isRentalStartValid = (startDate: string): boolean =>
  Boolean(startDate) && startDate >= minRentalStartDate()

export const isRentalEndValid = (startDate: string, endDate: string): boolean =>
  Boolean(startDate) && Boolean(endDate) && endDate >= startDate
