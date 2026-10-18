import type { InAppNotification } from '@/types/notification'

export const EVENT_LABEL: Record<string, string> = {
  BOOKING_SUBMITTED: 'Booking baru perlu persetujuan',
  BOOKING_APPROVED: 'Booking disetujui',
  BOOKING_REJECTED: 'Booking ditolak',
  BOOKING_SUPERVISOR_VALIDATED: 'Booking lolos validasi Supervisor',
  BOOKING_SUPERVISOR_REVISION: 'Booking dikembalikan Supervisor',
  OPERATOR_ASSIGNED: 'Penugasan Operator baru',
  USER_ROLE_UPDATED: 'Role akun diperbarui',
  PAYMENT_HOLD_STARTED: 'Pekerjaan dihentikan sementara (PAYMENT HOLD)',
  PAYMENT_HOLD_RESOLVED: 'PAYMENT HOLD dihentikan',
  PAYMENT_SUBMITTED: 'Pembayaran perlu verifikasi',
  PAYMENT_APPROVED: 'Pembayaran disetujui',
  PAYMENT_REJECTED: 'Pembayaran ditolak',
  TIMESHEET_SUBMITTED: 'Timesheet perlu divalidasi',
  REFUND_PENDING: 'Refund menunggu diproses',
  INVOICE_OVERDUE: 'Invoice jatuh tempo',
  OUTSTANDING_REMINDER: 'Pengingat outstanding',
}

export const labelFor = (item: InAppNotification): string =>
  (item.event && EVENT_LABEL[item.event]) || item.message || item.event || 'Notifikasi'

export const targetFor = (item: InAppNotification, role: 'ADMIN' | 'OWNER' | 'USER' | 'SUPERVISOR' | 'OPERATOR' | string = 'USER'): string => {
  if (item.link) return item.link

  const base =
    role === 'SUPERVISOR'
      ? '/supervisor'
      : role === 'OPERATOR'
        ? '/operator'
        : role === 'ADMIN' || role === 'OWNER'
          ? '/admin'
          : '/app'
  const evt = item.event || ''
  const t = item.type || ''

  if (role === 'SUPERVISOR') return evt.includes('BOOKING') ? `${base}/bookings` : `${base}/notifications`
  if (role === 'OPERATOR') {
    if (evt.includes('PAYMENT_HOLD') || evt.includes('OPERATOR')) return `${base}/assignments`
    return `${base}/notifications`
  }
  if (evt.includes('PAYMENT_HOLD')) return role === 'ADMIN' ? `${base}/rentals` : `${base}/invoices`
  
  // Specific entity targets using URL hashes/queries to locate elements (or main views)
  if (evt.includes('BOOKING') && role === 'ADMIN') return `${base}/bookings`
  if (evt.includes('BOOKING_APPROVED')) return `${base}/invoices` // "Approval booking -> Tagihan & Bayar"
  if (evt.includes('BOOKING')) return `${base}/bookings`
  
  if (evt.includes('PAYMENT_REJECTED')) return `${base}/invoices`
  if (evt.includes('PAYMENT') && role === 'ADMIN') return `${base}/payments`
  if (evt.includes('PAYMENT')) return `${base}/invoices`
  
  if (evt.includes('TIMESHEET')) return `${base}/timesheets`
  
  if (evt.includes('REFUND')) return `${base}/refunds`
  
  if (evt.includes('OUTSTANDING')) return `${base}/outstanding`
  
  if (evt.includes('INVOICE')) return `${base}/invoices`

  // Fallback to type mapping
  if (/PAYMENT/i.test(t)) return `${base}/payments`
  if (/TIMESHEET/i.test(t)) return `${base}/timesheets`
  if (/REFUND/i.test(t)) return `${base}/refunds`
  if (/INVOICE/i.test(t)) return `${base}/invoices`
  if (/BOOKING/i.test(t)) return `${base}/bookings`
  if (/OUTSTANDING/i.test(t)) return `${base}/outstanding`

  return `${base}/notifications`
}
