import React from 'react'
import { Badge } from './Badge'

export type BadgeTone = 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'

/**
 * Single source of truth for status → badge tone + label. Covers the enums
 * across the app (business status values are unchanged — only presentation).
 */
const MAP: Record<string, { tone: BadgeTone; label: string }> = {
  // Equipment
  AVAILABLE: { tone: 'success', label: 'Tersedia' },
  BOOKED: { tone: 'warning', label: 'Tersewa' },
  RESERVED: { tone: 'warning', label: 'Direservasi' },
  ON_SITE: { tone: 'default', label: 'Di Lokasi' },
  DISPATCHED: { tone: 'warning', label: 'Dikirim' },
  ARRIVED: { tone: 'secondary', label: 'Tiba' },
  ONGOING: { tone: 'success', label: 'Beroperasi' },
  RETURNING: { tone: 'warning', label: 'Kembali' },
  RETURNED: { tone: 'secondary', label: 'Kembali' },
  INSPECTION: { tone: 'default', label: 'Inspeksi' },
  MAINTENANCE: { tone: 'danger', label: 'Maintenance' },
  DAMAGED: { tone: 'danger', label: 'Rusak' },
  DECOMMISSIONED: { tone: 'outline', label: 'Nonaktif' },

  // Booking
  DRAFT: { tone: 'secondary', label: 'Draft' },
  PENDING_APPROVAL: { tone: 'warning', label: 'Menunggu Persetujuan' },
  APPROVED: { tone: 'default', label: 'Disetujui' },
  PAYMENT_PENDING: { tone: 'warning', label: 'Menunggu Bayar' },
  CONFIRMED: { tone: 'success', label: 'Terkonfirmasi' },
  COMPLETED: { tone: 'success', label: 'Selesai' },
  CANCELLED: { tone: 'danger', label: 'Dibatalkan' },
  REJECTED: { tone: 'danger', label: 'Ditolak' },
  EXPIRED: { tone: 'outline', label: 'Kedaluwarsa' },

  // Rental
  PENDING_ASSIGNMENT: { tone: 'warning', label: 'Menunggu Alokasi' },
  ASSIGNED: { tone: 'default', label: 'Terlokasi' },

  // Timesheet
  SUBMITTED: { tone: 'warning', label: 'Menunggu Konfirmasi' },

  // Invoice
  ISSUED: { tone: 'secondary', label: 'Terbit' },
  UNPAID: { tone: 'warning', label: 'Belum Bayar' },
  PARTIALLY_PAID: { tone: 'warning', label: 'Sebagian' },
  OVERDUE: { tone: 'danger', label: 'Jatuh Tempo' },
  PAID: { tone: 'success', label: 'Lunas' },
  OVERPAID: { tone: 'warning', label: 'Lebih Bayar' },

  // Payment / Refund
  PENDING: { tone: 'warning', label: 'Menunggu' },
  PROCESSING: { tone: 'default', label: 'Diproses' },
  FAILED: { tone: 'danger', label: 'Gagal' },
}

export interface StatusBadgeProps {
  status: string
  className?: string
  size?: 'sm' | 'md'
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({ status, className, size = 'sm' }) => {
  const entry = MAP[status]
  return (
    <Badge variant={entry?.tone ?? 'secondary'} size={size} className={className}>
      {entry?.label ?? status}
    </Badge>
  )
}
export default StatusBadge