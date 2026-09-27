import React, { useState, useEffect } from 'react'
import { Users } from 'lucide-react'
import { financeService } from '../services/refundService'
import type { CustomerOutstanding } from '@/types/refund'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

const fmt = (n: number): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n)

export const AdminOutstandingPage: React.FC = () => {
  const [rows, setRows] = useState<CustomerOutstanding[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [expanded, setExpanded] = useState<number | null>(null)

  useEffect(() => {
    const load = async () => {
      try {
        setRows(await financeService.allOutstanding())
      } catch (err: any) {
        setApiError(err?.message || 'Gagal memuat outstanding pelanggan.')
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Outstanding Pelanggan</h2>
        <p className="text-sm text-slate-500 mt-1">Kontrol kredit per pelanggan berdasarkan invoice belum lunas.</p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2, 3].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-56" /><Skeleton className="h-12 w-full rounded-xl" /></Card>
        ))}</div>
      ) : rows.length > 0 ? (
        <div className="space-y-4">
          {rows.map((row) => (
            <Card key={row.user_id} className="p-5">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3" role="button" onClick={() => setExpanded(expanded === row.user_id ? null : row.user_id)}>
                <div className="space-y-1">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-semibold text-slate-900">{row.customer_name ?? `Customer #${row.user_id}`}</span>
                    <Badge variant={row.eligible ? 'success' : 'danger'} size="sm">{row.eligible ? 'Eligible' : 'Outstanding'}</Badge>
                  </div>
                  <div className="text-xs text-slate-500">{row.open_invoice_count} invoice terbuka • {row.overdue_invoice_count} overdue</div>
                </div>
                <span className="text-xl font-bold text-rose-600">{fmt(row.total_outstanding)}</span>
              </div>

              {expanded === row.user_id && row.invoices.length > 0 && (
                <div className="mt-4 border-t border-slate-100 pt-3 space-y-2">
                  {row.invoices.map((line) => (
                    <div key={line.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs bg-slate-50 rounded-lg px-3 py-2">
                      <div className="flex items-center gap-2">
                        <span className="font-mono text-slate-900">{line.invoice_number}</span>
                        <Badge variant={line.status === 'OVERDUE' ? 'danger' : 'warning'} size="sm">{line.status}</Badge>
                      </div>
                      <div className="flex gap-x-4">
                        <span>Total {fmt(line.grand_total)}</span>
                        <span className="text-emerald-600">Bayar {fmt(line.paid_amount)}</span>
                        <span className="text-rose-600">Saldo {fmt(line.balance_amount)}</span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<Users className="w-12 h-12" />} title="Tidak Ada Outstanding" description="Tidak ada pelanggan dengan tagihan terbuka." />
      )}
    </div>
  )
}
export default AdminOutstandingPage