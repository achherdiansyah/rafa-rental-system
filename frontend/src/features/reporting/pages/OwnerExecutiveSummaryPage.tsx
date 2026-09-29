import React, { useCallback, useEffect, useState } from 'react'
import { TrendingUp, Wallet, CalendarCheck, Truck, Timer, Receipt, Undo2, BarChart3 } from 'lucide-react'
import { reportingService } from '../services/reportingService'
import type { DashboardReport } from '@/types/reporting'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import { Input } from '@/components/form/Input'
import { Button } from '@/components/ui/Button'

const idr = (n: number): string => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n || 0)
const num = (n: number): string => new Intl.NumberFormat('id-ID').format(n || 0)

function Kpi({ icon, label, value, sub, tone = 'text-slate-900' }: { icon: React.ReactNode; label: string; value: string; sub?: string; tone?: string }) {
  return (
    <Card className="p-5">
      <div className="flex items-center gap-2 text-slate-400 mb-3">{icon}</div>
      <p className="text-sm text-slate-500">{label}</p>
      <p className={`font-mono font-extrabold text-xl ${tone}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1">{sub}</p>}
    </Card>
  )
}

/**
 * Executive Summary — dashboard KPI owner (read-only overview). Detail tables
 * live in Laporan Pendapatan (/owner/revenue).
 */
export const OwnerExecutiveSummaryPage: React.FC = () => {
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [data, setData] = useState<DashboardReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)

  const load = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      setData(await reportingService.getDashboard({ from: from || undefined, to: to || undefined }))
    } catch (err: any) {
      setError(err?.message || 'Gagal memuat ringkasan eksekutif.')
    } finally {
      setLoading(false)
    }
  }, [from, to])

  useEffect(() => {
    load()
  }, [load])

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <BarChart3 size={22} className="text-primary-600" /> Executive Summary
          </h2>
          <p className="text-sm text-slate-500 mt-1">Ringkasan KPI bisnis read-only. Detail transaksi ada di Laporan Pendapatan.</p>
        </div>
        <div className="flex items-end gap-2">
          <Input label="Dari" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-36" />
          <Input label="Sampai" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-36" />
          <Button variant="outline" size="sm" onClick={load} disabled={loading}>Terapkan</Button>
        </div>
      </div>

      {error && <Alert variant="danger" title="Gagal Memuat Ringkasan">{error}</Alert>}

      {loading && !data ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {Array.from({ length: 8 }).map((_, i) => <Skeleton key={i} className="h-32 w-full rounded-2xl" />)}
        </div>
      ) : data ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Kpi
              icon={<TrendingUp size={18} />}
              label="Pembayaran Disetujui"
              value={idr(data.financial?.payments?.approved_amount ?? 0)}
              sub={`${num(data.financial?.payments?.approved_count ?? 0)} transaksi`}
              tone="text-emerald-600"
            />
            <Kpi
              icon={<Wallet size={18} />}
              label="Outstanding / Piutang"
              value={idr(data.outstanding?.total ?? 0)}
              sub={`${num(data.outstanding?.customer_count ?? 0)} customer`}
              tone="text-rose-600"
            />
            <Kpi
              icon={<CalendarCheck size={18} />}
              label="Booking"
              value={String(data.bookings?.total ?? 0)}
              sub={`${data.bookings?.by_status?.['CONFIRMED'] ?? 0} confirmed`}
            />
            <Kpi
              icon={<Truck size={18} />}
              label="Rental Aktif"
              value={String(data.rentals?.active ?? 0)}
              sub={`${num(data.rentals?.total ?? 0)} total`}
              tone="text-primary-600"
            />
          </div>
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <Kpi
              icon={<Timer size={18} />}
              label="Jam Kerja Timesheet"
              value={num(data.timesheet?.total_hours ?? 0)}
              sub={`${num(data.equipment?.utilization_hours ?? 0)} jam util`}
            />
            <Kpi
              icon={<Truck size={18} />}
              label="Utilisasi Armada"
              value={`${data.equipment?.available ?? 0}/${data.equipment?.fleet_total ?? 0}`}
              sub={`${num(data.equipment?.in_use ?? 0)} terpakai · ${num(data.equipment?.maintenance ?? 0)} maintenance`}
            />
            <Kpi
              icon={<Receipt size={18} />}
              label="Invoice"
              value={String((data.financial?.invoices ?? []).reduce((a: number, i: { count?: number }) => a + (i.count ?? 0), 0))}
              sub={`${idr((data.financial?.invoices ?? []).reduce((a: number, i: { grand_total?: number }) => a + Number(i.grand_total ?? 0), 0))} total`}
            />
            <Kpi
              icon={<Undo2 size={18} />}
              label="Refund"
              value={idr((data.financial?.refunds ?? []).reduce((a: number, r: { amount?: number }) => a + (r.amount ?? 0), 0))}
              sub={`${num((data.financial?.refunds ?? []).length)} status`}
            />
          </div>
          <p className="text-xs text-slate-400">Data agregat read-only dari <code>/api/v1/reports/dashboard</code>.</p>
        </div>
      ) : null}
    </div>
  )
}
export default OwnerExecutiveSummaryPage