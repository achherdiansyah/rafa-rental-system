import React, { useState, useEffect, useCallback } from 'react'
import { CalendarRange, RefreshCw, LayoutDashboard, TrendingUp, Wallet } from 'lucide-react'
import { reportingService } from '../services/reportingService'
import type { DashboardReport } from '@/types/reporting'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'

const fmtIdr = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(n ?? 0)

const fmtNum = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { maximumFractionDigits: 1 }).format(n ?? 0)

const defaultValue = (): string => new Date().toISOString().slice(0, 7) + '-01'

/* ---------- lightweight pure-CSS charts (no dependency) ---------- */

function MiniBars({ items, unit = '' }: { items: Array<{ label: string; value: number }>; unit?: string }) {
  const max = Math.max(...items.map((i) => i.value), 1)
  return (
    <div className="space-y-2.5">
      {items.map((i) => (
        <div key={i.label} className="space-y-1">
          <div className="flex items-center justify-between text-xs">
            <span className="text-slate-600">{i.label}</span>
            <span className="font-semibold text-slate-800">{unit === 'idr' ? fmtIdr(i.value) : fmtNum(i.value)}</span>
          </div>
          <div className="h-2 rounded-full bg-slate-100 overflow-hidden">
            <div className="h-full rounded-full bg-primary-600" style={{ width: `${Math.max((i.value / max) * 100, 2)}%` }} />
          </div>
        </div>
      ))}
    </div>
  )
}

function MiniDonut({ items }: { items: Array<{ label: string; value: number; color: string }> }) {
  const total = items.reduce((a, b) => a + b.value, 0) || 1
  const radius = 40
  const c = 2 * Math.PI * radius
  let offset = 0
  return (
    <div className="flex items-center gap-5">
      <svg width="110" height="110" viewBox="0 0 110 110" className="shrink-0">
        <circle cx="55" cy="55" r={radius} fill="none" strokeWidth="14" className="text-slate-100" stroke="currentColor" />
        {items.map((i) => {
          const frac = i.value / total
          const dash = frac * c
          const el = (
            <circle
              key={i.label}
              cx="55"
              cy="55"
              r={radius}
              fill="none"
              strokeWidth="14"
              stroke={i.color}
              strokeDasharray={`${dash} ${c - dash}`}
              strokeDashoffset={-offset}
              strokeLinecap="round"
              transform="rotate(-90 55 55)"
            />
          )
          offset += dash
          return el
        })}
      </svg>
      <div className="space-y-1.5">
        {items.map((i) => (
          <div key={i.label} className="flex items-center gap-2 text-xs">
            <span className="inline-block w-2.5 h-2.5 rounded-full" style={{ background: i.color }} />
            <span className="text-slate-600">{i.label}</span>
            <span className="font-semibold text-slate-800 ml-auto">{fmtNum(i.value)}</span>
          </div>
        ))}
      </div>
    </div>
  )
}

/* ---------- KPI ---------- */

function KpiCard({ label, value, sub, tone = 'text-slate-900' }: { label: string; value: string; sub?: string; tone?: string }) {
  return (
    <div className="card-surface p-5">
      <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{label}</p>
      <p className={`text-[26px] font-bold tracking-tight mt-2 ${tone}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1.5">{sub}</p>}
    </div>
  )
}

interface ChartCardProps {
  title: string
  children: React.ReactNode
}

function ChartCard({ title, children }: ChartCardProps) {
  return (
    <Card className="p-5 card-surface">
      <h4 className="font-semibold text-slate-900 pb-3 mb-4 border-b border-slate-100">{title}</h4>
      {children}
    </Card>
  )
}

/* ---------- Page ---------- */

export const AdminDashboardPage: React.FC = () => {
  const [data, setData] = useState<DashboardReport | null>(null)
  const [recent, setRecent] = useState<Record<string, unknown>[]>([])
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
  }, [load])

  // Aktivitas terbaru — read-only existing report (5 baris)
  useEffect(() => {
    let mounted = true
    const promise = reportingService.listReport?.('bookings', { per_page: 5 })
    if (!promise) return
    promise
      .then((res) => {
        if (mounted) setRecent(res.data ?? [])
      })
      .catch(() => undefined)
    return () => {
      mounted = false
    }
  }, [])

  const apply = () => load({ from, to })

  const invoiceTotal = data?.financial.invoices.reduce((acc, i) => acc + i.grand_total, 0) ?? 0
  const utilization = data
    ? [
        { label: 'Tersedia', value: data.equipment.available, color: '#10b981' },
        { label: 'Terpakai', value: data.equipment.in_use, color: '#0e87e9' },
        { label: 'Maintenance', value: data.equipment.maintenance, color: '#f59e0b' },
      ]
    : []
  const rentalStatus = data
    ? [
        { label: 'Aktif', value: data.rentals.active, color: '#0e87e9' },
        { label: 'Selesai', value: Math.max(data.rentals.total - data.rentals.active, 0), color: '#10b981' },
      ]
    : []

  return (
    <div className="space-y-8 max-w-7xl">
      {/* Header */}
      <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-100 text-accent-700">
              <LayoutDashboard size={18} />
            </span>
            Dashboard Operasional
          </h2>
          <p className="text-sm text-slate-500 mt-1">KPI ringkasan dari laporan agregat (read-only).</p>
        </div>
        {/* Date filter — compact group */}
        <div className="flex flex-wrap items-end gap-2">
          <div className="flex flex-wrap items-end gap-2 rounded-xl border border-slate-200 bg-white p-1.5">
            <Input label="Dari" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40 min-h-9" />
            <span className="hidden sm:inline text-slate-300 pb-2.5">—</span>
            <Input label="Sampai" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40 min-h-9" />
          </div>
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
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            {Array.from({ length: 8 }).map((_, i) => (
              <div key={i} className="card-surface p-5 space-y-3">
                <Skeleton className="h-3 w-20" />
                <Skeleton className="h-7 w-28" />
              </div>
            ))}
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {Array.from({ length: 4 }).map((_, i) => (
              <div key={i} className="card-surface p-5 space-y-3">
                <Skeleton className="h-4 w-32" />
                <Skeleton className="h-24 w-full" />
              </div>
            ))}
          </div>
        </div>
      ) : data ? (
        <>
          {/* KPI Operasional */}
          <section className="space-y-3">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">Operasional</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiCard label="Booking" value={String(data.bookings.total)} sub={`${data.bookings.by_status['CONFIRMED'] ?? 0} confirmed`} />
              <KpiCard label="Rental Aktif" value={String(data.rentals.active)} sub={`${data.rentals.total} total`} />
              <KpiCard label="Armada" value={`${data.equipment.available}/${data.equipment.fleet_total}`} sub={`${data.equipment.in_use} terpakai`} />
              <KpiCard label="Jam Kerja" value={fmtNum(data.timesheet.total_hours)} sub={`${data.equipment.utilization_hours} jam util*`} />
            </div>
          </section>

          {/* KPI Keuangan */}
          <section className="space-y-3">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">Keuangan</h3>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiCard label="Pembayaran Disetujui" value={fmtIdr(data.financial.payments.approved_amount)} sub={`${data.financial.payments.approved_count} transaksi`} tone="text-emerald-600" />
              <KpiCard label="Outstanding" value={fmtIdr(data.outstanding.total)} sub={`${data.outstanding.customer_count} customer`} tone="text-rose-600" />
              <KpiCard label="Invoice" value={String(data.financial.invoices.reduce((a, i) => a + i.count, 0))} sub={`${fmtIdr(invoiceTotal)} total`} />
              <KpiCard label="Refund" value={fmtIdr(data.financial.refunds.reduce((a, r) => a + r.amount, 0))} sub={`${data.financial.refunds.length} kategori`} />
            </div>
          </section>

          {/* Charts */}
          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <ChartCard title="Booking per Status">
              {Object.keys(data.bookings.by_status).length > 0 ? (
                <MiniBars items={Object.entries(data.bookings.by_status).map(([label, value]) => ({ label, value }))} />
              ) : (
                <p className="text-sm text-slate-400">Belum ada data pada periode.</p>
              )}
            </ChartCard>
            <ChartCard title="Revenue vs Pembayaran">
              <MiniBars
                items={[
                  { label: 'Total Invoice', value: invoiceTotal },
                  { label: 'Pembayaran Disetujui', value: data.financial.payments.approved_amount },
                  { label: 'Outstanding', value: data.outstanding.total },
                ]}
                unit="idr"
              />
            </ChartCard>
            <ChartCard title="Utilisasi Armada">
              <MiniDonut items={utilization} />
            </ChartCard>
            <ChartCard title="Rental Status">
              <MiniDonut items={rentalStatus} />
            </ChartCard>
          </section>

          {/* Ringkasan Operasional */}
          <section className="space-y-4">
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400">Ringkasan Operasional</h3>
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Rental per Proyek</h4>
                {data.rentals.by_project.length > 0 ? (
                  <div className="space-y-2.5">
                    {data.rentals.by_project.map((p) => (
                      <div key={p.project_id} className="flex items-center justify-between text-sm">
                        <span className="text-slate-700 truncate">{p.project_name}</span>
                        <span className="font-semibold text-slate-900">{p.total}</span>
                      </div>
                    ))}
                  </div>
                ) : (
                  <p className="text-sm text-slate-400">Belum ada data rental pada periode.</p>
                )}
              </Card>
              <Card className="p-5 card-surface">
                <h4 className="font-semibold text-slate-900 pb-3 mb-3 border-b border-slate-100">Trend Jam Kerja</h4>
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
                        <span className="text-slate-700">{inv.status} <span className="text-slate-400">({inv.count})</span></span>
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

          {/* Aktivitas terbaru */}
          <section>
            <h3 className="text-xs font-semibold uppercase tracking-wider text-slate-400 mb-3">Aktivitas Terbaru</h3>
            <Card className="card-surface divide-y divide-slate-100">
              {recent.length > 0 ? (
                recent.slice(0, 5).map((row, idx) => (
                  <div key={idx} className="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                    <div className="flex items-center gap-2 min-w-0">
                      <TrendingUp size={15} className="text-slate-400 shrink-0" />
                      <span className="text-slate-700 truncate">
                        {(row.booking_code as string) || 'Booking'} · {(row.project_name as string) || '—'}
                      </span>
                    </div>
                    <span className="text-xs text-slate-500 shrink-0">{String(row.status)}</span>
                  </div>
                ))
              ) : (
                <p className="px-5 py-6 text-sm text-slate-400 flex items-center gap-2">
                  <Wallet size={15} /> Belum ada aktivitas pada periode saat ini.
                </p>
              )}
            </Card>
          </section>
        </>
      ) : null}
    </div>
  )
}
export default AdminDashboardPage