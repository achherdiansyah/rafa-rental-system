import React, { useCallback, useEffect, useState } from 'react'
import { LayoutDashboard, CalendarRange, RefreshCw } from 'lucide-react'
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

function StatCard({ label, value, sub, tone = 'text-slate-900' }: { label: string; value: string; sub?: string; tone?: string }) {
  return (
    <div className="card-surface p-5">
      <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{label}</p>
      <p className={`text-[26px] font-bold tracking-tight mt-2 ${tone}`}>{value}</p>
      {sub && <p className="text-xs text-slate-400 mt-1.5">{sub}</p>}
    </div>
  )
}

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

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <Card className="p-5 card-surface">
      <h3 className="font-semibold text-slate-900 pb-3 mb-4 border-b border-slate-100">{title}</h3>
      {children}
    </Card>
  )
}

export const OwnerExecutiveSummaryPage: React.FC = () => {
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
      setApiError(err?.message || 'Gagal memuat ringkasan eksekutif.')
    } finally {
      setIsLoading(false)
    }
  }, [])

  useEffect(() => {
    load({ from, to })
  }, [load])

  const apply = () => load({ from, to })

  const invoiceValue = data?.financial.invoices.reduce((a, i) => a + i.grand_total, 0) ?? 0
  const overdueCount = data?.financial.invoices.filter((i) => i.status === 'OVERDUE').reduce((a, i) => a + i.count, 0) ?? 0
  const overdueValue = data?.financial.invoices.filter((i) => i.status === 'OVERDUE').reduce((a, i) => a + i.grand_total, 0) ?? 0

  const fleet = data
    ? [
        { label: 'Tersedia', value: data.equipment.available, color: '#10b981' },
        { label: 'Terpakai', value: data.equipment.in_use, color: '#0e87e9' },
        { label: 'Maintenance', value: data.equipment.maintenance, color: '#f59e0b' },
      ]
    : []

  return (
    <div className="space-y-6 max-w-7xl">
      {/* Welcome banner */}
      <section className="rounded-2xl border border-accent-300 bg-accent-300 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">Selamat Datang, Owner Bisnis</h2>
          <p className="text-sm text-slate-800 mt-1">Pantau kinerja rental, pendapatan, dan utilisasi armada.</p>
        </div>
        <span className="hidden sm:flex h-11 w-11 items-center justify-center rounded-lg bg-accent-500 text-slate-900 shrink-0">
          <LayoutDashboard size={20} />
        </span>
      </section>

      {/* Date filter horizontal */}
      <section className="card-surface px-4 py-3">
        <div className="flex flex-wrap items-end gap-3">
          <div className="space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Dari</label>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-44 min-h-9" aria-label="Tanggal dari" />
          </div>
          <span className="hidden sm:inline text-slate-300 pb-2">—</span>
          <div className="space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sampai</label>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-44 min-h-9" aria-label="Tanggal sampai" />
          </div>
          <div className="flex items-end gap-2">
            <Button variant="primary" size="sm" className="gap-1.5" onClick={apply}>
              <CalendarRange size={14} /> Terapkan
            </Button>
            <Button variant="outline" size="sm" className="gap-1.5" onClick={() => load({})}>
              <RefreshCw size={14} /> Reset
            </Button>
          </div>
        </div>
      </section>

      {apiError && <Alert variant="danger" title="Gagal Memuat Ringkasan">{apiError}</Alert>}

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
          {/* KPI */}
          <section className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <StatCard label="Booking" value={String(data.bookings.total)} sub={`${data.bookings.by_status['CONFIRMED'] ?? 0} confirmed`} />
            <StatCard label="Rental Aktif" value={String(data.rentals.active)} sub={`${data.rentals.total} total`} />
            <StatCard label="Nilai Invoice" value={fmtIdr(invoiceValue)} sub={`${data.financial.invoices.reduce((a, i) => a + i.count, 0)} invoice`} />
            <StatCard label="Pembayaran" value={fmtIdr(data.financial.payments.approved_amount)} sub={`${data.financial.payments.approved_count} disetujui`} tone="text-emerald-600" />
            <StatCard label="Outstanding" value={fmtIdr(data.outstanding.total)} sub={`${data.outstanding.customer_count} customer`} tone="text-rose-600" />
            <StatCard label="Overdue" value={String(overdueCount)} sub={`${fmtIdr(overdueValue)} nilai`} tone="text-rose-600" />
            <StatCard label="Jam Kerja" value={fmtNum(data.timesheet.total_hours)} sub={`${data.equipment.utilization_hours} jam util*`} />
            <StatCard label="Utilisasi Armada" value={`${data.equipment.in_use}/${data.equipment.fleet_total}`} sub={`${data.equipment.available} tersedia`} />
          </section>

          {/* Charts */}
          <section className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <Panel title="Booking & Rental">
              <div className="space-y-4">
                <MiniBars items={Object.entries(data.bookings.by_status).map(([label, value]) => ({ label, value }))} />
                <MiniBars items={[
                  { label: 'Rental Aktif', value: data.rentals.active },
                  { label: 'Rental Selesai', value: Math.max(data.rentals.total - data.rentals.active, 0) },
                ]} />
              </div>
            </Panel>
            <Panel title="Invoice vs Pembayaran">
              <MiniBars
                unit="idr"
                items={[
                  { label: 'Total Invoice', value: invoiceValue },
                  { label: 'Pembayaran Disetujui', value: data.financial.payments.approved_amount },
                  { label: 'Outstanding', value: data.outstanding.total },
                  { label: 'Overdue', value: overdueValue },
                ]}
              />
            </Panel>
            <Panel title="Utilisasi Armada">
              <MiniDonut items={fleet} />
            </Panel>
            <Panel title="Komposisi Armada per Status">
              <MiniBars items={fleet.map((f) => ({ label: f.label, value: f.value }))} />
            </Panel>
            <Panel title="Top Project Location">
              {data.rentals.by_project.length > 0 ? (
                <MiniBars items={data.rentals.by_project.map((p) => ({ label: p.project_name, value: p.total }))} />
              ) : (
                <p className="text-sm text-slate-400">Belum ada data rental pada periode.</p>
              )}
            </Panel>
          </section>

          {/* Business summary */}
          <section className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <Panel title="Status Bisnis">
              <div className="space-y-2">
                {Object.entries(data.bookings.by_status).map(([status, count]) => (
                  <div key={status} className="flex items-center justify-between text-sm">
                    <span className="text-slate-600">{status}</span>
                    <span className="font-semibold text-slate-800">{count}</span>
                  </div>
                ))}
              </div>
            </Panel>
            <Panel title="Equipment Performance">
              <div className="space-y-2">
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Tersedia</span><span className="font-semibold text-slate-800">{data.equipment.available}</span></div>
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Terpakai</span><span className="font-semibold text-slate-800">{data.equipment.in_use}</span></div>
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Maintenance</span><span className="font-semibold text-slate-800">{data.equipment.maintenance}</span></div>
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Jam Utilisasi</span><span className="font-semibold text-slate-800">{fmtNum(data.equipment.utilization_hours)}</span></div>
              </div>
            </Panel>
            <Panel title="Outstanding & Overdue">
              <div className="space-y-2">
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Outstanding</span><span className="font-semibold text-rose-600">{fmtIdr(data.outstanding.total)}</span></div>
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Overdue (invoice)</span><span className="font-semibold text-rose-600">{overdueCount}</span></div>
                <div className="flex items-center justify-between text-sm"><span className="text-slate-600">Nilai Overdue</span><span className="font-semibold text-rose-600">{fmtIdr(overdueValue)}</span></div>
              </div>
            </Panel>
          </section>
        </>
      ) : null}
    </div>
  )
}
export default OwnerExecutiveSummaryPage