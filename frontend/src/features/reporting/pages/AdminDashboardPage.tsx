import React, { useState, useEffect, useCallback } from 'react'
import { LayoutDashboard, CalendarRange, RefreshCw } from 'lucide-react'
import { reportingService } from '../services/reportingService'
import type { DashboardReport } from '@/types/reporting'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'

const fmtIdr = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 0 }).format(n ?? 0)

const fmtNum = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(n ?? 0)

function KpiCard({ label, value, sub, tone = 'text-slate-900' }: { label: string; value: string; sub?: string; tone?: string }) {
  return (
    <div className="card-surface hoverable p-5">
      <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">{label}</p>
      <p className={`text-2xl font-bold mt-2 ${tone}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1.5">{sub}</p>}
    </div>
  )
}

const defaultValue = (): string => new Date().toISOString().slice(0, 7) + '-01'

export const AdminDashboardPage: React.FC = () => {
  const [data, setData] = useState<DashboardReport | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [from, setFrom] = useState(defaultValue())
  const [to, setTo] = useState(() => new Date().toISOString().slice(0, 10))

  const load = useCallback(async (params?: { from?: string; to?: string }) => {
    setIsLoading(true)
    setApiError(null)
    try {
      setData(await reportingService.getDashboard(params))
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat dashboard.')
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    load({ from, to })
  }, [])

  const apply = () => load({ from, to })

  return (
    <div className="space-y-8">
      {/* Header */}
      <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-100 text-accent-700"><LayoutDashboard size={18} /></span>
            Dashboard Operasional
          </h2>
          <p className="text-sm text-slate-500 mt-1">KPI ringkasan dari laporan agregat (read-only).</p>
        </div>
        <div className="flex items-end gap-2 flex-wrap">
          <Input label="Dari" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40" />
          <Input label="Sampai" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40" />
          <Button variant="primary" size="sm" className="gap-1.5" onClick={apply}>
            <CalendarRange size={14} /> Terapkan
          </Button>
          <Button variant="outline" size="sm" className="gap-1.5" onClick={() => load({})}>
            <RefreshCw size={14} /> Reset
          </Button>
        </div>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Dashboard">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="card-surface p-5 space-y-3">
                <Skeleton className="h-4 w-24" />
                <Skeleton className="h-8 w-32" />
              </div>
            ))}
          </div>
        </div>
      ) : data ? (
        <>
          {/* KPIs — Overview */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-400">Operasional</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiCard label="Booking" value={String(data.bookings.total)} sub={`${data.bookings.by_status['CONFIRMED'] ?? 0} confirmed`} />
              <KpiCard label="Rental Aktif" value={String(data.rentals.active)} sub={`${data.rentals.total} total`} tone="text-primary-600" />
              <KpiCard label="Armada" value={`${data.equipment.available}/${data.equipment.fleet_total}`} sub={`${data.equipment.in_use} terpakai • ${data.equipment.maintenance} maintenance`} />
              <KpiCard label="Jam Kerja" value={fmtNum(data.timesheet.total_hours)} sub={`${data.equipment.utilization_hours} jam util*`} />
            </div>
          </section>

          {/* KPIs — Keuangan */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-400">Keuangan</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiCard label="Pembayaran Disetujui" value={fmtIdr(data.financial.payments.approved_amount)} sub={`${data.financial.payments.approved_count} transaksi`} tone="text-emerald-600" />
              <KpiCard label="Outstanding" value={fmtIdr(data.outstanding.total)} sub={`${data.outstanding.customer_count} customer`} tone="text-rose-600" />
              <KpiCard label="Invoice" value={String((data.financial.invoices ?? []).reduce((acc, i) => acc + i.count, 0))} sub={`${fmtIdr(data.financial.invoices?.reduce((acc, i) => acc + i.grand_total, 0))} total`} />
              <KpiCard label="Refund" value={fmtIdr(data.financial.refunds?.reduce((acc, r) => acc + r.amount, 0) ?? 0)} sub={`${(data.financial.refunds ?? []).length} status`} />
            </div>
          </section>

          {/* Detail summary */}
          <section className="space-y-3">
            <h3 className="text-sm font-semibold uppercase tracking-wide text-slate-400">Ringkasan</h3>
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Status Booking</h4>
                <div className="flex flex-wrap gap-2">
                  {Object.entries(data.bookings.by_status).map(([status, count]) => (
                    <span key={status} className="text-xs bg-slate-50 border border-slate-100 rounded-lg px-2.5 py-1.5">
                      <strong className="text-slate-900">{count}</strong> <span className="text-slate-500">{status}</span>
                    </span>
                  ))}
                </div>
              </Card>

              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Rental per Proyek</h4>
                {data.rentals.by_project.length > 0 ? (
                  <div className="space-y-2.5">
                    {data.rentals.by_project.map((p) => (
                      <div key={p.project_id} className="flex items-center justify-between text-sm">
                        <span className="text-slate-700">{p.project_name}</span>
                        <span className="font-semibold text-slate-900">{p.total}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-slate-400">Belum ada data rental pada periode.</p>
                )}
              </Card>

              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Trend Jam Kerja per Bulan</h4>
                {data.timesheet.by_month.length > 0 ? (
                  <div className="flex items-end gap-2 h-28">
                    {data.timesheet.by_month.map((m) => {
                      const max = Math.max(...data.timesheet.by_month.map((x) => x.total_hours), 1)
                      return (
                        <div key={m.month} className="flex flex-col items-center flex-1">
                          <span className="text-[10px] text-slate-500 mb-1">{fmtNum(m.total_hours)}</span>
                          <div className="w-full bg-accent-400 rounded-t-lg" style={{ height: `${Math.max((m.total_hours / max) * 100, 4)}px` }} />
                          <span className="text-[10px] text-slate-400 mt-1">{m.month}</span>
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <p className="text-sm text-slate-400">Belum ada jam kerja pada periode.</p>
                )}
              </Card>

              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Invoice per Status</h4>
                {data.financial.invoices.length > 0 ? (
                  <div className="space-y-2.5">
                    {data.financial.invoices.map((inv) => (
                      <div key={inv.status} className="flex items-center justify-between text-sm">
                        <span className="text-slate-700">
                          {inv.status} <span className="text-slate-400">({inv.count})</span>
                        </span>
                        <span className="text-slate-900 font-medium">{fmtIdr(inv.grand_total)}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-slate-400">Belum ada invoice pada periode.</p>
                )}
              </Card>
            </div>
          </section>
        </>
      ) : null}
    </div>
  )
}
export default AdminDashboardPage