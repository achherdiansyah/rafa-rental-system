import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { AlertTriangle } from 'lucide-react'
import { api } from '@/lib/api'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

interface PaymentHoldRow {
  id: number
  rental_id: number
  booking_code: string | null
  project_name: string | null
  status: string
  reason_code: string
  trigger_invoice_id: number | null
  trigger_invoice_number: string | null
  outstanding_amount: number
  started_at: string | null
  resolved_at: string | null
  resolution_reason: string | null
  created_by_name: string | null
  resolved_by_name: string | null
}

export const AdminPaymentHoldsPage: React.FC = () => {
  const [holds, setHolds] = useState<PaymentHoldRow[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [status, setStatus] = useState('')
  const [reason, setReason] = useState('')
  const [rentalQuery, setRentalQuery] = useState('')
  const [date, setDate] = useState('')

  useEffect(() => {
    api.get<PaymentHoldRow[]>('/admin/payment-holds')
      .then((res) => setHolds(res.data ?? []))
      .catch((err) => setError(err?.message || 'Gagal memuat PAYMENT HOLD.'))
      .finally(() => setIsLoading(false))
  }, [])

  const filtered = holds.filter((h) => {
    if (status && h.status !== status) return false
    if (reason && h.reason_code !== reason) return false
    if (rentalQuery && !`${h.booking_code ?? ''} ${h.rental_id}`.toLowerCase().includes(rentalQuery.toLowerCase())) return false
    if (date && !(h.started_at ?? '').startsWith(date)) return false
    return true
  })

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <AlertTriangle className="text-rose-600" size={24} /> Riwayat PAYMENT HOLD
        </h2>
        <p className="text-sm text-slate-500 mt-1">Hold aktif dan riwayat hold yang sudah diselesaikan. Data tidak dihapus.</p>
      </div>

      <Card className="p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <Select label="Status" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Semua</option>
          <option value="ACTIVE">ACTIVE</option>
          <option value="RESOLVED">RESOLVED</option>
        </Select>
        <Select label="Alasan" value={reason} onChange={(e) => setReason(e.target.value)}>
          <option value="">Semua</option>
          <option value="INVOICE_COUNT_3">3 Invoice Belum Lunas</option>
          <option value="OLDEST_INVOICE_72H">Invoice Tertua ≥72 Jam</option>
        </Select>
        <Input label="Cari Rental / Booking" value={rentalQuery} onChange={(e) => setRentalQuery(e.target.value)} placeholder="Booking code / rental ID" />
        <Input label="Tanggal Mulai" type="date" value={date} onChange={(e) => setDate(e.target.value)} />
      </Card>

      {error && (
        <Alert variant="danger" title="Gagal Memuat">
          <div className="flex flex-wrap items-center justify-between gap-2">
            <span>{error}</span>
            <button type="button" onClick={() => window.location.reload()} className="font-semibold underline">Muat Ulang</button>
          </div>
        </Alert>
      )}

      {isLoading ? (
        <Card className="p-5"><Skeleton className="h-24 w-full" /></Card>
      ) : filtered.length === 0 ? (
        <EmptyState icon={<AlertTriangle className="w-12 h-12" />} title="Tidak Ada Data" description="Tidak ada PAYMENT HOLD yang cocok dengan filter." />
      ) : (
        <div className="space-y-3">
          {filtered.map((h) => (
            <Card key={h.id} className="p-4 space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <Badge variant={h.status === 'ACTIVE' ? 'danger' : 'success'} size="sm">{h.status}</Badge>
                <span className="font-mono text-sm font-bold text-slate-900">{h.booking_code ?? `Rental #${h.rental_id}`}</span>
                <Badge variant="warning" size="sm">{h.reason_code}</Badge>
                {h.project_name && <span className="text-xs text-slate-500">{h.project_name}</span>}
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs text-slate-600">
                <p>Invoice pemicu: <strong>{h.trigger_invoice_number ?? '-'}</strong></p>
                <p>Outstanding: <strong>Rp {Number(h.outstanding_amount).toLocaleString('id-ID')}</strong></p>
                <p>Mulai: {h.started_at ? new Date(h.started_at).toLocaleString('id-ID') : '-'}</p>
                <p>Selesai: {h.resolved_at ? new Date(h.resolved_at).toLocaleString('id-ID') : '-'}</p>
                <p>Dibuat oleh: {h.created_by_name ?? 'System/Scheduler'}</p>
                <p>Resume oleh: {h.resolved_by_name ?? '-'}</p>
              </div>
              {h.resolution_reason && <p className="text-xs text-slate-500">Alasan resume: {h.resolution_reason}</p>}
              <div className="flex gap-3 text-xs">
                <Link className="text-primary-700 hover:underline" to="/admin/rentals">Buka Rental</Link>
                {h.trigger_invoice_id && (
                  <Link className="text-primary-700 hover:underline" to="/admin/invoices">Buka Invoice</Link>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

export default AdminPaymentHoldsPage
