import React from 'react'
import { ReportExplorer, type ReportColumn, type ReportTab } from '../components/ReportExplorer'

const idr = (v: unknown): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 2 }).format(Number(v) || 0)

const columnsMap: Record<string, ReportColumn[]> = {
  invoices: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan', sortable: true },
    { key: 'invoice_date', label: 'Tanggal', sortable: true },
    { key: 'grand_total', label: 'Total', sortable: true, render: (r) => idr(r.grand_total) },
    { key: 'paid_total', label: 'Dibayar', render: (r) => idr(r.paid_total) },
    { key: 'balance', label: 'Sisa', render: (r) => idr(r.balance) },
  ],
  payments: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'payment_date', label: 'Tanggal', sortable: true },
    { key: 'sender_name', label: 'Pengirim' },
    { key: 'amount', label: 'Nominal', sortable: true, render: (r) => idr(r.amount) },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'reference', label: 'Referensi' },
  ],
  partials: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'grand_total', label: 'Total', sortable: true, render: (r) => idr(r.grand_total) },
    { key: 'paid_total', label: 'Dibayar', render: (r) => idr(r.paid_total) },
    { key: 'balance', label: 'Sisa', render: (r) => idr(r.balance) },
  ],
  outstanding: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan', sortable: true },
    { key: 'project_name', label: 'Proyek' },
    { key: 'due_at', label: 'Jatuh Tempo', sortable: true },
    { key: 'balance', label: 'Outstanding', render: (r) => idr(r.balance) },
  ],
  overpayments: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'overpay_amount', label: 'Kelebihan Bayar', render: (r) => idr(r.overpay_amount) },
  ],
  refunds: [
    { key: 'invoice_number', label: 'Invoice', sortable: true },
    { key: 'status', label: 'Status', sortable: true },
    { key: 'customer_name', label: 'Pelanggan' },
    { key: 'amount', label: 'Nominal', sortable: true, render: (r) => idr(r.amount) },
    { key: 'source', label: 'Sumber' },
  ],
}

const tabs: ReportTab[] = [
  { key: 'invoices', label: 'Invoice' },
  { key: 'payments', label: 'Pembayaran' },
  { key: 'partials', label: 'Sebagian' },
  { key: 'outstanding', label: 'Outstanding' },
  { key: 'overpayments', label: 'Kelebihan Bayar' },
  { key: 'refunds', label: 'Refund' },
]

/**
 * Laporan Pendapatan — detailed financial report (tables/filter/sort/page/export).
 * Distinct from Executive Summary which is KPI cards only.
 */
export const OwnerRevenueReportPage: React.FC = () => (
  <ReportExplorer
    title="Laporan Pendapatan"
    subtitle="Rincian invoice, pembayaran, outstanding, kelebihan bayar, dan refund berbasis pembayaran disetujui."
    tabs={tabs}
    columnByTab={columnsMap}
  />
)
export default OwnerRevenueReportPage