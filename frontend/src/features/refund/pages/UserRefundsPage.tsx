import React, { useState, useEffect } from 'react'
import { Wallet } from 'lucide-react'
import { refundService } from '../services/refundService'
import type { Refund } from '@/types/refund'
import { REFUND_STEPS } from '@/types/refund'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  PENDING: 'warning',
  APPROVED: 'secondary',
  PROCESSING: 'warning',
  COMPLETED: 'success',
  FAILED: 'danger',
}

const fmt = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n ?? 0)

export const UserRefundsPage: React.FC = () => {
  const [refunds, setRefunds] = useState<Refund[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const load = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await refundService.getRefunds({ per_page: 50 })
      setRefunds(res.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat refund.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Refund Saya</h2>
        <p className="text-sm text-slate-500 mt-1">Pantau status pengembalian dana beserta progresnya.</p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Refund">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-52" /><Skeleton className="h-14 w-full rounded-xl" /></Card>
        ))}</div>
      ) : refunds.length > 0 ? (
        <div className="space-y-4">
          {refunds.map((r) => {
            const stepIdx = REFUND_STEPS.indexOf(r.status)
            return (
              <Card key={r.id} className="p-5">
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                  <div className="space-y-1.5">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-semibold text-slate-900">{fmt(r.amount)}</span>
                      <Badge variant={STATUS_VARIANT[r.status] ?? 'secondary'} size="sm">{r.status}</Badge>
                      <span className="text-xs text-slate-400">{r.source === 'OVERPAYMENT' ? 'Kelebihan Bayar' : 'Pembatalan'} • {r.invoice?.invoice_number ?? `#${r.invoice_id}`}</span>
                    </div>
                    <p className="text-sm text-slate-600">{r.reason}</p>
                    {r.approval_reason && <p className="text-xs text-slate-500">Alasan persetujuan: {r.approval_reason}</p>}
                    {r.failure_reason && (
                      <div className="text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-2 py-1 inline-flex">{r.failure_reason}</div>
                    )}
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-400">
                      {r.approved_at && <span>Disetujui {new Date(r.approved_at).toLocaleString('id-ID')}</span>}
                      {r.processed_at && <span>Diproses {new Date(r.processed_at).toLocaleString('id-ID')}</span>}
                      {r.completed_at && <span>Selesai {new Date(r.completed_at).toLocaleString('id-ID')}</span>}
                      {r.transfer_reference && <span>Ref: {r.transfer_reference}</span>}
                    </div>
                  </div>
                </div>

                {(r.status === 'APPROVED' || r.status === 'PROCESSING' || r.status === 'COMPLETED') && (
                  <div className="mt-4 border-t border-slate-100 pt-3">
                    <div className="flex items-center gap-1">
                      {REFUND_STEPS.map((s, i) => {
                        const active = i <= stepIdx
                        return (
                          <div key={s} className="flex items-center flex-1 last:flex-none">
                            <div className={`h-1.5 flex-1 rounded-full ${active ? 'bg-emerald-500' : 'bg-slate-200'}`} />
                            {i < REFUND_STEPS.length - 1 && <span className="w-1.5 h-1.5 rounded-full bg-slate-200" />}
                          </div>
                        )
                      })}
                    </div>
                    <div className="flex justify-between mt-1.5 text-[10px] text-slate-400">
                      <span>Diajukan</span><span>Disetujui</span><span>Diproses</span><span>Selesai</span>
                    </div>
                  </div>
                )}
              </Card>
            )
          })}
        </div>
      ) : (
        <EmptyState icon={<Wallet className="w-12 h-12" />} title="Belum Ada Refund"
          description="Refund muncul dari pembatalan setelah bayar atau kelebihan pembayaran." />
      )}
    </div>
  )
}
export default UserRefundsPage