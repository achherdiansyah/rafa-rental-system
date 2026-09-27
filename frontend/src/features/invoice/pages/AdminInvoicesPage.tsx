import React, { useState, useEffect } from 'react'
import { Receipt, Clock } from 'lucide-react'
import { invoiceService } from '../services/invoiceService'
import type { Invoice } from '@/types/invoice'
import { invoiceTypeLabel } from '@/types/invoice'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary', ISSUED: 'secondary', UNPAID: 'warning', PARTIALLY_PAID: 'warning',
  PAID: 'success', OVERPAID: 'danger', OVERDUE: 'danger', CANCELLED: 'outline',
}

const fmt = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n ?? 0)

export const AdminInvoicesPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [filter, setFilter] = useState('')
  const [apiError, setApiError] = useState<string | null>(null)

  const [extendTarget, setExtendTarget] = useState<Invoice | null>(null)
  const [extendHours, setExtendHours] = useState('24')
  const [actingId, setActingId] = useState<number | null>(null)

  const loadInvoices = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await invoiceService.getInvoices({ status: filter || undefined, per_page: 50 })
      setInvoices(res.data ?? [])
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat invoice.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadInvoices()
  }, [filter])

  const handleExtend = async () => {
    if (!extendTarget) return
    setActingId(extendTarget.id)
    try {
      const res = await invoiceService.extendDeadline(extendTarget.id, Number(extendHours || 24))
      showSuccessToast(`Deadline ${res.invoice_number} diperpanjang ${extendHours || 24} jam.`)
      setExtendTarget(null)
      loadInvoices()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memperpanjang deadline.')
    } finally {
      setActingId(null)
    }
  }

  const extendable = (inv: Invoice) => inv.status === 'ISSUED' || inv.status === 'UNPAID' || inv.status === 'OVERDUE'

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Invoice</h2>
          <p className="text-sm text-slate-500 mt-1">Monitor tagihan, saldo, kelebihan bayar, dan deadline pembayaran.</p>
        </div>
        <Select className="w-44" value={filter} onChange={(e) => setFilter(e.target.value)}
          options={[
            { value: '', label: 'Semua Status' },
            { value: 'ISSUED', label: 'Diterbitkan' },
            { value: 'UNPAID', label: 'Belum Dibayar' },
            { value: 'PARTIALLY_PAID', label: 'Sebagian' },
            { value: 'PAID', label: 'Lunas' },
            { value: 'OVERPAID', label: 'Kelebihan Bayar' },
            { value: 'OVERDUE', label: 'Lewat Deadline' },
            { value: 'DRAFT', label: 'Draft' },
            { value: 'CANCELLED', label: 'Dibatalkan' },
          ]} />
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Invoice">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2, 3].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-52" /><Skeleton className="h-12 w-full rounded-xl" /></Card>
        ))}</div>
      ) : invoices.length > 0 ? (
        <div className="space-y-4">
          {invoices.map((inv) => (
            <Card key={inv.id} className="p-5">
              <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-3">
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-sm font-bold text-slate-900">{inv.invoice_number}</span>
                    <Badge variant={STATUS_VARIANT[inv.status] ?? 'secondary'} size="sm">{inv.status}</Badge>
                    <span className="text-xs text-slate-400">{invoiceTypeLabel[inv.invoice_type]}</span>
                  </div>
                  <div className="text-xs text-slate-500">
                    {inv.booking?.booking_code ?? '-'} • {inv.booking?.project?.project_name ?? '-'}
                  </div>
                  <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                    <span className="text-slate-600">Total <strong className="text-slate-900">{fmt(inv.grand_total)}</strong></span>
                    <span className="text-emerald-600">Dibayar {fmt(inv.paid_amount)}</span>
                    <span className="text-rose-600">Saldo {fmt(inv.balance_amount)}</span>
                  </div>
                  {inv.status === 'OVERPAID' && (
                    <div className="text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-2 py-1 inline-flex items-center gap-1">
                      Kelebihan bayar {fmt(inv.overpayment_amount)} — settlement manual menunggu (refund belum otomatis)
                    </div>
                  )}
                  {inv.due_at && (
                    <div className="text-xs text-slate-500 inline-flex items-center gap-1">
                      <Clock size={12} /> Deadline {new Date(inv.due_at).toLocaleString('id-ID')}
                    </div>
                  )}
                </div>

                {extendable(inv) && (
                  <Button variant="outline" size="sm" className="gap-1.5 shrink-0" onClick={() => { setExtendTarget(inv); setExtendHours('24') }}>
                    <Clock size={14} /> Perpanjang Deadline
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<Receipt className="w-12 h-12" />} title="Tidak Ada Invoice" description="Tidak ada invoice pada filter ini." />
      )}

      <ConfirmDialog
        isOpen={extendTarget !== null}
        onClose={() => !actingId && setExtendTarget(null)}
        onConfirm={handleExtend}
        title="Perpanjang Deadline Pembayaran"
        message={`Invoice ${extendTarget?.invoice_number ?? ''} — atur tambahan jam (0–168). Perpanjangan dicatat audit.`}
        confirmText="Perpanjang"
        cancelText="Batal"
        variant="primary"
        isLoading={actingId !== null}
      >
        <div className="w-full mt-3">
          <Input label="Tambah Jam" type="number" min={0} max={168} value={extendHours}
            onChange={(e) => setExtendHours(e.target.value)} />
        </div>
      </ConfirmDialog>
    </div>
  )
}
export default AdminInvoicesPage