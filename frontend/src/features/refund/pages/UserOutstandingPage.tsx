import React, { useState, useEffect } from 'react'
import { Scale } from 'lucide-react'
import { financeService } from '../services/refundService'
import type { CustomerOutstanding } from '@/types/refund'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'

const fmt = (n: number): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n)

export const UserOutstandingPage: React.FC = () => {
  const [data, setData] = useState<CustomerOutstanding | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    const load = async () => {
      try {
        setData(await financeService.myOutstanding())
      } catch (err: any) {
        setApiError(err?.message || 'Gagal memuat saldo tagihan.')
      } finally {
        setIsLoading(false)
      }
    }
    load()
  }, [])

  if (isLoading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-6 w-64" />
        <Card className="p-5 space-y-3"><Skeleton className="h-20 w-full rounded-xl" /></Card>
      </div>
    )
  }

  if (apiError) return <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>
  if (!data) return null

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Tagihan Berjalan (Outstanding)</h2>
        <p className="text-sm text-slate-500 mt-1">Ringkasan tagihan yang belum lunas beserta saldo per invoice.</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <Card className="p-4">
          <p className="text-xs text-slate-500">Total Outstanding</p>
          <p className="text-xl font-bold text-slate-900 mt-1">{fmt(data.total_outstanding)}</p>
        </Card>
        <Card className="p-4">
          <p className="text-xs text-slate-500">Invoice Terbuka</p>
          <p className="text-xl font-bold text-slate-900 mt-1">{data.open_invoice_count}</p>
        </Card>
        <Card className="p-4">
          <p className="text-xs text-slate-500">Lewat Jatuh Tempo</p>
          <p className="text-xl font-bold text-rose-600 mt-1">{data.overdue_invoice_count}</p>
        </Card>
      </div>

      <div className={`inline-flex items-center gap-2 text-sm rounded-lg px-3 py-1.5 ${data.eligible ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'}`}>
        <Scale size={14} />
        {data.eligible ? 'Tidak ada tagihan terbuka' : 'Ada saldo tagihan yang belum diselesaikan'}
      </div>

      {data.invoices.length > 0 ? (
        <div className="space-y-3">
          {data.invoices.map((line) => (
            <Card key={line.id} className="p-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-mono text-sm font-semibold text-slate-900">{line.invoice_number}</span>
                  <Badge variant={line.status === 'OVERDUE' ? 'danger' : 'warning'} size="sm">{line.status}</Badge>
                </div>
                <div className="flex items-center gap-x-5 text-sm flex-wrap">
                  <span className="text-slate-600">Total {fmt(line.grand_total)}</span>
                  <span className="text-emerald-600">Dibayar {fmt(line.paid_amount)}</span>
                  <span className="text-rose-600 font-medium">Saldo {fmt(line.balance_amount)}</span>
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <p className="text-sm text-slate-400">Tidak ada invoice terbuka.</p>
      )}
    </div>
  )
}
export default UserOutstandingPage