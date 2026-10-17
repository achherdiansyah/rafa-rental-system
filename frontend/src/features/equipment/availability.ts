import type { EquipmentModel } from '@/types/equipment'

export type AvailabilityTone = 'success' | 'warning' | 'danger'

export interface AvailabilityLabel {
  text: string
  tone: AvailabilityTone
}

const formatDate = (iso: string): string => {
  const d = new Date(`${iso}T00:00:00`)
  if (Number.isNaN(d.getTime())) return iso
  return d.toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

/**
 * Single source for the catalog/detail availability wording so the card and the
 * detail page never drift. Uses only real API data (counts + status + end date).
 */
export const availabilityLabel = (model: EquipmentModel): AvailabilityLabel => {
  const available = model.available_units_count ?? 0

  if (available > 0) {
    return { text: `${available} unit tersedia`, tone: 'success' }
  }

  if (model.availability_status === 'RENTED') {
    if (model.rented_until) {
      return { text: `Sedang Disewa hingga ${formatDate(model.rented_until)}`, tone: 'warning' }
    }
    return { text: 'Sedang Disewa', tone: 'warning' }
  }

  if (model.availability_status === 'BOOKED') {
    if (model.booked_from) {
      return { text: `Dipesan mulai ${formatDate(model.booked_from)}`, tone: 'warning' }
    }
    return { text: 'Sudah Dipesan', tone: 'warning' }
  }

  if (model.availability_status === 'MAINTENANCE') {
    return { text: 'Unit Maintenance', tone: 'warning' }
  }

  return { text: 'Tidak Tersedia', tone: 'danger' }
}