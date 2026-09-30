import React, { useCallback, useEffect, useState } from 'react'
import { Search, History, Eye, RefreshCw } from 'lucide-react'
import { reportingService } from '../services/reportingService'
import { StatusBadge } from '@/components/ui/StatusBadge'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Modal } from '@/components/ui/Modal'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Pagination } from '@/components/data-display/Pagination'

interface AuditRow {
  rental_id?: number | string
  booking_code?: string
  status?: string
  started_at?: string
  completed_at?: string | null
  customer_name?: string
  customer_id?: number | string
  project_name?: string
  city?: string
  total_hours?: number
  paid_total?: number
  [key: string]: unknown
}

const fmt = (n: unknown): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(Number(n) || 0)

const fmtDt = (iso?: string | null): string => {
  if (!iso) return '—'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '—'
  return d.toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' })
}

export const OwnerAuditTrailPage: React.FC = () => {
  const [rows, setRows] = useState<AuditRow[]>([])
  const [lastPage, setLastPage] = useState(1)
  const [page, setPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [search, setSearch] = useState('')
  const [status, setStatus] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [detail, setDetail] = useState<AuditRow | null>(null)

  const load = useCallback(async (p = page) => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await reportingService.listReport('activity', {
        per_page: 10,
        page: p,
        from: from || undefined,
        to: to || undefined,
        status: status || undefined,
      })
      setRows(res.data ?? [])
      setLastPage(res.meta?.last_page ?? 1)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat audit trail.')
    } finally {
      setIsLoading(false)
    }
  }, [page, from, to, status])

  useEffect(() => {
    load(page)
  }, [load])

  const reset = () => {
    setSearch('')
    setStatus('')
    setFrom('')
    setTo('')
    setPage(1)
    load(1)
  }

  const statuses = Array.from(new Set(rows.map((r) => r.status).filter(Boolean))) as string[]
  const filtered = rows.filter((r) => {
    const q = search.trim().toLowerCase()
    if (!q) return true
    return [r.booking_code, r.customer_name, r.project_name, r.city].some((v) => String(v ?? '').toLowerCase().includes(q))
  })

  return (
    <div className="space-y-6 max-w-7xl">
      {/* Header */}
      <section className="rounded-2xl border border-accent-300 bg-accent-300 p-6">
        <h2 className="text-xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <History size={20} className="text-slate-800" /> Audit Trail Logs
        </h2>
        <p className="text-sm text-slate-800 mt-1">Aktivitas operasional rental, timesheet, invoice, dan pembayaran (read-only).</p>
      </section>

      {/* Filters */}
      <section className="card-surface px-4 py-3">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex-1 min-w-[200px] space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Cari</label>
            <div className="relative">
              <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <Input
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Booking, pelanggan, proyek…"
                className="pl-9 min-h-9"
                aria-label="Cari audit trail"
              />
            </div>
          </div>
          <div className="space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Status</label>
            <Select value={status} onChange={(e) => setStatus(e.target.value)} className="w-44 min-h-9" aria-label="Filter status">
              <option value="">Semua Status</option>
              {statuses.map((s) => (
                <option key={s} value={s}>{s}</option>
              ))}
            </Select>
          </div>
          <div className="space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Dari</label>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="w-40 min-h-9" aria-label="Tanggal dari" />
          </div>
          <div className="space-y-1">
            <label className="block text-[11px] font-semibold uppercase tracking-wide text-slate-400">Sampai</label>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="w-40 min-h-9" aria-label="Tanggal sampai" />
          </div>
          <div className="flex items-end gap-2">
            <Button variant="outline" size="sm" className="gap-1.5" onClick={reset}>
              <RefreshCw size={14} /> Reset
            </Button>
          </div>
        </div>
      </section>

      {apiError && <Alert variant="danger" title="Gagal Memuat Audit Trail">{apiError}</Alert>}

      {/* Table */}
      <section className="card-surface overflow-hidden">
        {isLoading ? (
          <div className="p-5 space-y-3">
            {Array.from({ length: 6 }).map((_, i) => (
              <Skeleton key={i} className="h-10 w-full rounded-lg" />
            ))}
          </div>
        ) : filtered.length === 0 ? (
          <EmptyState
            title="Belum Ada Aktivitas"
            description="Tidak ditemukan aktivitas pada periode/filter saat ini."
          />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-400">
                  <th className="px-4 py-3 font-semibold">Waktu</th>
                  <th className="px-4 py-3 font-semibold">Aktor / Customer</th>
                  <th className="px-4 py-3 font-semibold">Aksi (Status)</th>
                  <th className="px-4 py-3 font-semibold">Entitas</th>
                  <th className="px-4 py-3 font-semibold text-right">Jam</th>
                  <th className="px-4 py-3 font-semibold text-right">Dibayar</th>
                  <th className="px-4 py-3 font-semibold text-right">Detail</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-50">
                {filtered.map((r) => (
                  <tr key={String(r.rental_id ?? r.booking_code ?? Math.random())} className="hover:bg-slate-50/60">
                    <td className="px-4 py-3 text-slate-500 whitespace-nowrap">{fmtDt(r.started_at)}</td>
                    <td className="px-4 py-3 text-slate-800">{r.customer_name || '—'}</td>
                    <td className="px-4 py-3"><StatusBadge status={String(r.status ?? '')} /></td>
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-800">{r.project_name || '—'}</p>
                      <p className="text-xs text-slate-400">{r.booking_code || ''}{r.city ? ` · ${r.city}` : ''}</p>
                    </td>
                    <td className="px-4 py-3 text-right font-mono text-slate-700">{(r.total_hours ?? 0)}</td>
                    <td className="px-4 py-3 text-right font-mono text-slate-700">{fmt(r.paid_total)}</td>
                    <td className="px-4 py-3 text-right">
                      <Button size="sm" variant="ghost" onClick={() => setDetail(r)} aria-label={`Detail ${r.booking_code || r.rental_id}`}>
                        <Eye size={15} />
                      </Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
        {filtered.length > 0 && lastPage > 1 && (
          <div className="border-t border-slate-100 px-4 py-3">
            <Pagination currentPage={page} totalPages={lastPage} onPageChange={setPage} />
          </div>
        )}
      </section>

      {/* Detail modal */}
      <Modal isOpen={!!detail} onClose={() => setDetail(null)} title="Detail Audit Trail" size="md">
        {detail ? (
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div><p className="text-xs text-slate-400">Waktu Mulai</p><p className="font-medium text-slate-800">{fmtDt(detail.started_at)}</p></div>
            <div><p className="text-xs text-slate-400">Selesai</p><p className="font-medium text-slate-800">{fmtDt(detail.completed_at)}</p></div>
            <div><p className="text-xs text-slate-400">Customer</p><p className="font-medium text-slate-800">{detail.customer_name || '—'}</p></div>
            <div><p className="text-xs text-slate-400">Status</p><StatusBadge status={String(detail.status ?? '')} /></div>
            <div className="sm:col-span-2"><p className="text-xs text-slate-400">Booking</p><p className="font-medium text-slate-800">{detail.booking_code || '—'}</p></div>
            <div className="sm:col-span-2"><p className="text-xs text-slate-400">Proyek</p><p className="font-medium text-slate-800">{detail.project_name || '—'}{detail.city ? ` — ${detail.city}` : ''}</p></div>
            <div><p className="text-xs text-slate-400">Total Jam</p><p className="font-mono font-medium text-slate-800">{(detail.total_hours ?? 0)}</p></div>
            <div><p className="text-xs text-slate-400">Dibayar</p><p className="font-mono font-medium text-slate-800">{fmt(detail.paid_total)}</p></div>
          </div>
        ) : null}
      </Modal>
    </div>
  )
}
export default OwnerAuditTrailPage