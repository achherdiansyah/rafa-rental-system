import React from 'react'
import { ReportExplorer, type ReportColumn } from '../components/ReportExplorer'

const idr = (v: unknown): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v) || 0)

const columnsMap: Record<string, ReportColumn[]> = {
  invoices: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'grand_total', label: 'Total', sortable: true, render: (r) => idr(r.grand_total) },
    { key: 'paid_total', label: 'Dibayar', render: (r) => idr(r.paid_total) },
    { key: 'balance', label: 'Sisa', render: (r) => idr(r.balance) },
  ],
  payments: [
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'amount', label: 'Nominal', sortable: true, render: (r) => idr(r.amount) },
    { key: 'payment_date', label: 'Tanggal', sortable: true },
    { key: 'sender_name', label: 'Pengirim' },
    { key: 'reference', label: 'Referensi' },
  ],
  partials: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'grand_total', label: 'Total', sortable: true, render: (r) => idr(r.grand_total) },
    { key: 'paid_total', label: 'Dibayar', render: (r) => idr(r.paid_total) },
    { key: 'balance', label: 'Sisa', render: (r) => idr(r.balance) },
    { key: 'approved_count', label: 'Jml Bayar' },
  ],
  outstanding: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'project_name', label: 'Proyek' },
    { key: 'due_at', label: 'Jatuh Tempo' },
    { key: 'balance', label: 'Outstanding', render: (r) => idr(r.balance) },
  ],
  overpayments: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status' },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'grand_total', label: 'Total', render: (r) => idr(r.grand_total) },
    { key: 'overpayment_amount', label: 'Kelebihan', sortable: true, render: (r) => idr(r.overpayment_amount) },
    { key: 'refunded_total', label: 'Refund', render: (r) => idr(r.refunded_total) },
  ],
  refunds: [
    { key: 'invoice_number', label: 'Invoice' },
    { key: 'source', label: 'Sumber' },
    { key: 'amount', label: 'Nominal', sortable: true, render: (r) => idr(r.amount) },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'completed_at', label: 'Selesai' },
  ],
}

export const AdminFinancialReportsPage: React.FC = () => (
  <ReportExplorer
    title="Laporan Finansial"
    subtitle="Invoice, pembayaran, sebagian, outstanding, kelebihan bayar, dan refund (basis approved)."
    columnByTab={columnsMap}
    tabs={[
      { key: 'invoices', label: 'Invoice', statusOptions: [
        { value: 'DRAFT', label: 'Draft' }, { value: 'ISSUED', label: 'Diterbitkan' },
        { value: 'UNPAID', label: 'Belum Bayar' }, { value: 'PARTIALLY_PAID', label: 'Sebagian' },
        { value: 'PAID', label: 'Lunas' }, { value: 'OVERPAID', label: 'Kelebihan' },
        { value: 'OVERDUE', label: 'Overdue' }, { value: 'CANCELLED', label: 'Dibatalkan' },
      ] },
      { key: 'payments', label: 'Pembayaran', statusOptions: [
        { value: 'SUBMITTED', label: 'Diajukan' }, { value: 'APPROVED', label: 'Disetujui' },
        { value: 'REJECTED', label: 'Ditolak' },
      ] },
      { key: 'partials', label: 'Pembayaran Sebagian' },
      { key: 'outstanding', label: 'Outstanding' },
      { key: 'overpayments', label: 'Kelebihan Bayar' },
      { key: 'refunds', label: 'Refund', statusOptions: [
        { value: 'PENDING', label: 'Menunggu' }, { value: 'APPROVED', label: 'Disetujui' },
        { value: 'PROCESSING', label: 'Diproses' }, { value: 'COMPLETED', label: 'Selesai' },
        { value: 'FAILED', label: 'Gagal' },
      ] },
    ]}
  />
)
export default AdminFinancialReportsPage
