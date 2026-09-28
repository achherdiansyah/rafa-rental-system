import React from 'react'
import { ReportExplorer, type ReportColumn } from '../components/ReportExplorer'

const idr = (v: unknown): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v) || 0)

const hours = (v: unknown): string => `${Number(v ?? 0).toLocaleString('id-ID', { maximumFractionDigits: 2 })} jam`

const columnsMap: Record<string, ReportColumn[]> = {
  bookings: [
    { key: 'booking_code', label: 'Kode Booking', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'project_name', label: 'Proyek' },
    { key: 'total_amount', label: 'Total', sortable: true, render: (r) => idr(r.total_amount) },
    { key: 'created_at', label: 'Dibuat', sortable: true },
  ],
  timesheets: [
    { key: 'report_date', label: 'Tanggal', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'unit_serial', label: 'Unit' },
    { key: 'model', label: 'Model' },
    { key: 'project_name', label: 'Proyek' },
    { key: 'total_work_hours', label: 'Jam Kerja', sortable: true, render: (r) => hours(r.total_work_hours) },
  ],
  rentals: [
    { key: 'rental_id', label: 'Rental', sortable: true },
    { key: 'booking_code', label: 'Booking' },
    { key: 'rental_status', label: 'Status', sortable: true },
    { key: 'unit_serial', label: 'Unit' },
    { key: 'model', label: 'Model' },
    { key: 'total_work_hours', label: 'Jam Buatan', sortable: true, render: (r) => hours(r.total_work_hours) },
  ],
  activity: [
    { key: 'booking_code', label: 'Kode Booking' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'project_name', label: 'Proyek' },
    { key: 'total_hours', label: 'Jam', sortable: true, render: (r) => hours(r.total_hours) },
    { key: 'paid_total', label: 'Terbayar', sortable: true, render: (r) => idr(r.paid_total) },
  ],
  equipment: [
    { key: 'serial_number', label: 'Serial' },
    { key: 'plate_number', label: 'Plat' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'model', label: 'Model' },
    { key: 'total_work_hours', label: 'Jam Total', sortable: true, render: (r) => hours(r.total_work_hours) },
  ],
}

export const AdminOperationalReportsPage: React.FC = () => (
  <ReportExplorer
    title="Laporan Operasional"
    subtitle="Booking, timesheet jam aktual, utilasi rental, aktivitas, dan armada."
    columnByTab={columnsMap}
    tabs={[
      { key: 'bookings', label: 'Booking', statusOptions: [
        { value: 'DRAFT', label: 'Draft' }, { value: 'SUBMITTED', label: 'Submitted' },
        { value: 'PENDING_APPROVAL', label: 'Menunggu Persetujuan' }, { value: 'APPROVED', label: 'Approved' },
        { value: 'CONFIRMED', label: 'Confirmed' }, { value: 'REJECTED', label: 'Ditolak' },
        { value: 'CANCELLED', label: 'Dibatalkan' }, { value: 'EXPIRED', label: 'Kedaluwarsa' },
      ] },
      { key: 'timesheets', label: 'Timesheet', statusOptions: [
        { value: 'DRAFT', label: 'Draft' }, { value: 'SUBMITTED', label: 'Diajukan' },
        { value: 'APPROVED', label: 'Disetujui' }, { value: 'REJECTED', label: 'Ditolak' },
      ] },
      { key: 'rentals', label: 'Utilisasi', statusOptions: [
        { value: 'ASSIGNED', label: 'Ditugaskan' }, { value: 'DISPATCHED', label: 'Dikirim' },
        { value: 'ARRIVED', label: 'Tiba' }, { value: 'ONGOING', label: 'Berjalan' },
        { value: 'DEMOBILIZING', label: 'Pengembalian' }, { value: 'RETURN_INSPECTED', label: 'Inspeksi' },
        { value: 'COMPLETED', label: 'Selesai' },
      ] },
      { key: 'activity', label: 'Aktivitas Proyek' },
      { key: 'equipment', label: 'Armada' },
    ]}
  />
)
export default AdminOperationalReportsPage
