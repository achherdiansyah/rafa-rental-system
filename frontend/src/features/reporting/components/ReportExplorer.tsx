import React, { useState, useEffect, useCallback } from 'react'
import { Download, Search, ChevronLeft, ChevronRight, ArrowUp, ArrowDown, FileSpreadsheet } from 'lucide-react'
import { reportingService, type ReportQuery, type ReportType } from '../services/reportingService'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select, type SelectOption } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

export interface ReportColumn {
  key: string
  label: string
  sortable?: boolean
  render?: (row: Record<string, unknown>) => React.ReactNode
}

export interface ReportTab {
  key: ReportType
  label: string
  statusOptions?: SelectOption[]
}

interface ReportExplorerProps {
  title: string
  subtitle: string
  tabs: ReportTab[]
  columnByTab: Record<string, ReportColumn[]>
}

const cellText = (v: unknown): string => (v === null || v === undefined ? '' : String(v))

const fmtValue = (v: unknown): string => {
  if (typeof v === 'number') return new Intl.NumberFormat('id-ID', { maximumFractionDigits: 2 }).format(v)
  return cellText(v)
}

/**
 * Generic row-level report explorer: tabs, server filters (date/status/id),
 * client search, sortable columns, pagination, CSV export, expandable detail.
 * Loads ONLY the active tab (no waterfall / duplicate requests on mount).
 */
export const ReportExplorer: React.FC<ReportExplorerProps> = ({ title, subtitle, tabs, columnByTab }) => {
  const { error: showErrorToast } = useToast()
  const [active, setActive] = useState<ReportType>(tabs[0].key)
  const [draft, setDraft] = useState({ from: '', to: '', status: '', customerId: '' })
  const [filters, setFilters] = useState({ from: '', to: '', status: '', customerId: '' })
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [sortBy, setSortBy] = useState<string | null>(null)
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc')
  const [data, setData] = useState<Record<string, unknown>[]>([])
  const [total, setTotal] = useState(0)
  const [lastPage, setLastPage] = useState(1)
  const [isLoading, setIsLoading] = useState(true)
  const [exporting, setExporting] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)
  const [expanded, setExpanded] = useState<number | null>(null)

  const activeTab = tabs.find((t) => t.key === active) ?? tabs[0]
  const columns = columnByTab[active] ?? []

  const buildParams = useCallback((): ReportQuery => {
    const params: ReportQuery = { per_page: 20, page, sort_by: sortBy ?? undefined, sort_dir: sortDir }
    if (filters.from) params.from = filters.from
    if (filters.to) params.to = filters.to
    if (filters.status) params.status = filters.status
    if (filters.customerId) params.customer_id = filters.customerId
    return params
  }, [filters, page, sortBy, sortDir])

  const load = useCallback(async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await reportingService.listReport(active, buildParams())
      setData(res.data)
      setTotal(res.meta.total)
      setLastPage(res.meta.last_page)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat laporan.')
    } finally {
      setIsLoading(false)
    }
  }, [active, buildParams])

  useEffect(() => {
    load()
  }, [load])

  const changeTab = (key: ReportType) => {
    setActive(key)
    setPage(1)
    setQ('')
    setSortBy(null)
    setSortDir('desc')
  }

  const applyFilters = () => {
    // Apply only on explicit action; typing in the inputs must NOT trigger a
    // server refetch per keystroke (avoids duplicate requests + skeleton flicker).
    setFilters(draft)
    setPage(1)
  }

  const toggleSort = (col: ReportColumn) => {
    if (!col.sortable) return
    if (sortBy === col.key) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'))
    } else {
      setSortBy(col.key)
      setSortDir('asc')
    }
    setPage(1)
  }

  const handleExport = async () => {
    setExporting(true)
    try {
      const url = await reportingService.downloadCsv(active, buildParams())
      const a = document.createElement('a')
      a.href = url
      a.download = `rafa-report-${active}.csv`
      document.body.appendChild(a)
      a.click()
      a.remove()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengekspor laporan.')
    } finally {
      setExporting(false)
    }
  }

  const filteredRows = q
    ? data.filter((row) => Object.values(row).some((v) => cellText(v).toLowerCase().includes(q.toLowerCase())))
    : data

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">{title}</h2>
          <p className="text-sm text-slate-500 mt-1">{subtitle}</p>
        </div>
        <div className="flex items-center gap-2 flex-wrap">
          <div className="relative">
            <Search size={14} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              aria-label="Cari"
              className="flex h-10 w-52 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm pl-8 focus:outline-none focus:ring-2 focus:ring-primary-600"
              placeholder="Cari di halaman ini…"
              value={q}
              onChange={(e) => setQ(e.target.value)}
            />
          </div>
          <Button variant="primary" size="sm" className="gap-1.5" onClick={handleExport} isLoading={exporting}>
            <Download size={14} /> Ekspor CSV
          </Button>
        </div>
      </div>

      <div className="border-b border-slate-200">
        <nav aria-label="Tabs Laporan" className="-mb-px flex space-x-6 overflow-x-auto">
          {tabs.map((tab) => (
            <button
              key={tab.key}
              role="tab"
              aria-selected={active === tab.key}
              onClick={() => changeTab(tab.key)}
              className={`whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition-colors cursor-pointer ${
                active === tab.key ? 'border-primary-600 text-primary-600' : 'border-transparent text-slate-500 hover:text-slate-700'
              }`}
            >
              {tab.label}
            </button>
          ))}
        </nav>
      </div>

      <div className="flex flex-wrap items-end gap-2">
        <Input label="Dari" type="date" value={draft.from} onChange={(e) => setDraft((f) => ({ ...f, from: e.target.value }))} />
        <Input label="Sampai" type="date" value={draft.to} onChange={(e) => setDraft((f) => ({ ...f, to: e.target.value }))} />
        {activeTab.statusOptions && (
          <Select label="Status" value={draft.status} onChange={(e) => setDraft((f) => ({ ...f, status: e.target.value }))} options={activeTab.statusOptions} placeholder="Semua Status" />
        )}
        <Input label="Customer ID" type="number" value={draft.customerId} onChange={(e) => setDraft((f) => ({ ...f, customerId: e.target.value }))} />
        <Button variant="outline" size="sm" onClick={applyFilters}>
          Terapkan
        </Button>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Laporan">{apiError}</Alert>}

      <Card className="p-0 overflow-hidden">
        {isLoading ? (
          <div className="p-5 space-y-3">{Array.from({ length: 5 }).map((_, i) => <Skeleton key={i} className="h-8 w-full rounded-lg" />)}</div>
        ) : filteredRows.length > 0 ? (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-100 bg-slate-50">
                  <th className="px-4 py-3 text-left w-8" />
                  {columns.map((col) => (
                    <th
                      key={col.key}
                      className={`px-4 py-3 text-left font-medium text-slate-600 ${col.sortable ? 'cursor-pointer select-none hover:text-primary-600' : ''}`}
                      onClick={() => toggleSort(col)}
                    >
                      <span className="inline-flex items-center gap-1">
                        {col.label}
                        {col.sortable && sortBy === col.key && (sortDir === 'asc' ? <ArrowUp size={12} /> : <ArrowDown size={12} />)}
                      </span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {filteredRows.map((row, idx) => {
                  const key = String(row.id ?? row.key ?? idx)
                  const rowId = row.id as number
                  const isExpanded = expanded === rowId
                  return (
                    <React.Fragment key={`${active}-${key}`}>
                      <tr className="border-b border-slate-50 hover:bg-slate-50/60 cursor-pointer" onClick={() => setExpanded(isExpanded ? null : rowId)}>
                        <td className="px-4 py-3">
                          <button aria-label="Detail baris" className="text-slate-300 hover:text-primary-600">
                            {isExpanded ? '▾' : '▸'}
                          </button>
                        </td>
                        {columns.map((col) => (
                          <td key={col.key} className="px-4 py-3 text-slate-700 whitespace-nowrap">
                            {col.render ? col.render(row) : fmtValue(row[col.key])}
                          </td>
                        ))}
                      </tr>
                      {isExpanded && (
                        <tr className="bg-slate-50/50">
                          <td colSpan={columns.length + 1} className="px-6 py-3">
                            <p className="text-xs text-slate-500">
                              {Object.entries(row)
                                .filter(([k]) => !String(k).startsWith('_') && !['id', 'booking_code'].includes(k))
                                .map(([k, v]) => `${k}: ${fmtValue(v)}`)
                                .join(' • ')}
                            </p>
                          </td>
                        </tr>
                      )}
                    </React.Fragment>
                  )
                })}
              </tbody>
            </table>
          </div>
        ) : (
          <EmptyState icon={<FileSpreadsheet className="w-12 h-12" />} title="Tidak Ada Data" description="Tidak ada baris pada filter ini." />
        )}

        <div className="flex items-center justify-between px-4 py-3 border-t border-slate-100 text-xs text-slate-500">
          <span>{total} baris</span>
          <div className="flex items-center gap-2">
            <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage((p) => Math.max(1, p - 1))}>
              <ChevronLeft size={14} /> Prev
            </Button>
            <span>Hal {page} / {Math.max(lastPage, 1)}</span>
            <Button variant="outline" size="sm" disabled={page >= lastPage} onClick={() => setPage((p) => p + 1)}>
              Next <ChevronRight size={14} />
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}
export default ReportExplorer