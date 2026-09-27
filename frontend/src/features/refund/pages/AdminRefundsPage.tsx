import React, { useState, useEffect } from 'react'
import { RotateCcw } from 'lucide-react'
import { refundService } from '../services/refundService'
import type { Refund } from '@/types/refund'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Textarea } from '@/components/form/Textarea'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useAuth } from '@/hooks/useAuth'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  PENDING: 'warning', APPROVED: 'secondary', PROCESSING: 'warning', COMPLETED: 'success', FAILED: 'danger',
}

const fmt = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n ?? 0)

export const AdminRefundsPage: React.FC = () => {
  const { user } = useAuth()
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const isOwner = user?.role === 'OWNER'

  const [refunds, setRefunds] = useState<Refund[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [filter, setFilter] = useState('PENDING')
  const [apiError, setApiError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const [approveTarget, setApproveTarget] = useState<Refund | null>(null)
  const [approveReason, setApproveReason] = useState('')
  const [processTarget, setProcessTarget] = useState<Refund | null>(null)
  const [processForm, setProcessForm] = useState({ customer_bank_info: '', transfer_reference: '' })
  const [processError, setProcessError] = useState('')
  const [completeTarget, setCompleteTarget] = useState<Refund | null>(null)
  const [completeRef, setCompleteRef] = useState('')
  const [proofFile, setProofFile] = useState<File | null>(null)
  const [failTarget, setFailTarget] = useState<Refund | null>(null)
  const [failReason, setFailReason] = useState('')
  const [failError, setFailError] = useState('')

  const load = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await refundService.getRefunds({ status: filter || undefined, per_page: 50 })
      setRefunds(res.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat antrean refund.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [filter])

  const handleApprove = async () => {
    if (!approveTarget) return
    setActingId(approveTarget.id)
    try {
      await refundService.approve(approveTarget.id, approveReason || undefined)
      showSuccessToast('Refund disetujui.')
      setApproveTarget(null)
      setApproveReason('')
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyetujui refund.')
    } finally {
      setActingId(null)
    }
  }

  const handleProcess = async () => {
    if (!processTarget) return
    if (!processForm.customer_bank_info.trim()) {
      setProcessError('Informasi rekening tujuan wajib diisi.')
      return
    }
    setActingId(processTarget.id)
    try {
      await refundService.process(processTarget.id, {
        customer_bank_info: processForm.customer_bank_info,
        transfer_reference: processForm.transfer_reference || undefined,
      })
      showSuccessToast('Refund diproses (transfer bank).')
      setProcessTarget(null)
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memproses refund.')
    } finally {
      setActingId(null)
    }
  }

  const handleComplete = async () => {
    if (!completeTarget) return
    if (!proofFile) {
      showErrorToast('Bukti transfer wajib dilampirkan.')
      return
    }
    setActingId(completeTarget.id)
    try {
      await refundService.complete(completeTarget.id, { transfer_reference: completeRef || undefined, proof: proofFile })
      showSuccessToast('Refund selesai.')
      setCompleteTarget(null)
      setProofFile(null)
      setCompleteRef('')
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyelesaikan refund.')
    } finally {
      setActingId(null)
    }
  }

  const handleFail = async () => {
    if (!failTarget) return
    if (failReason.trim().length < 5) {
      setFailError('Alasan kegagalan minimal 5 karakter.')
      return
    }
    setActingId(failTarget.id)
    try {
      await refundService.fail(failTarget.id, failReason)
      showSuccessToast('Refund ditandai gagal.')
      setFailTarget(null)
      setFailReason('')
      load()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menandai refund.')
    } finally {
      setActingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Refund</h2>
          <p className="text-sm text-slate-500 mt-1">Setujui (Owner), proses, dan selesaikan refund via transfer bank.</p>
        </div>
        <Select className="w-44" value={filter} onChange={(e) => setFilter(e.target.value)}
          options={[
            { value: 'PENDING', label: 'Menunggu Persetujuan' },
            { value: 'APPROVED', label: 'Disetujui' },
            { value: 'PROCESSING', label: 'Diproses' },
            { value: 'COMPLETED', label: 'Selesai' },
            { value: 'FAILED', label: 'Gagal' },
            { value: '', label: 'Semua Status' },
          ]} />
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2, 3].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-56" /><Skeleton className="h-12 w-full rounded-xl" /></Card>
        ))}</div>
      ) : refunds.length > 0 ? (
        <div className="space-y-4">
          {refunds.map((r) => (
            <Card key={r.id} className="p-5">
              <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-3">
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant={STATUS_VARIANT[r.status] ?? 'secondary'} size="sm">{r.status}</Badge>
                    <span className="font-semibold text-slate-900">{fmt(r.amount)}</span>
                    <span className="text-xs text-slate-400">{r.source === 'OVERPAYMENT' ? 'Kelebihan Bayar' : 'Pembatalan'} • {r.invoice?.invoice_number ?? `#${r.invoice_id}`}</span>
                  </div>
                  <p className="text-sm text-slate-600">{r.reason}</p>
                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    {r.invoice?.booking_code && <span>{r.invoice.booking_code}</span>}
                    {r.invoice?.project?.project_name && <span>{r.invoice.project.project_name}</span>}
                    {r.customer_bank_info && <span>Bank: {r.customer_bank_info}</span>}
                    {r.transfer_reference && <span>Ref: {r.transfer_reference}</span>}
                    {r.failure_reason && <span className="text-rose-600">{r.failure_reason}</span>}
                  </div>
                </div>

                <div className="flex flex-wrap items-center gap-2 shrink-0">
                  {r.status === 'PENDING' && isOwner && (
                    <Button variant="primary" size="sm" isLoading={actingId === r.id} onClick={() => { setApproveTarget(r); setApproveReason('') }}>
                      Setujui
                    </Button>
                  )}
                  {r.status === 'APPROVED' && !isOwner && (
                    <Button variant="primary" size="sm" onClick={() => { setProcessTarget(r); setProcessError(''); setProcessForm({ customer_bank_info: '', transfer_reference: '' }) }}>
                      Proses
                    </Button>
                  )}
                  {r.status === 'PROCESSING' && !isOwner && (
                    <Button variant="primary" size="sm" onClick={() => { setCompleteTarget(r); setCompleteRef(r.transfer_reference ?? ''); setProofFile(null) }}>
                      Selesaikan
                    </Button>
                  )}
                  {['PENDING', 'APPROVED', 'PROCESSING'].includes(r.status) && !isOwner && (
                    <Button variant="danger" size="sm" onClick={() => { setFailTarget(r); setFailReason(''); setFailError('') }}>
                      Gagalkan
                    </Button>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<RotateCcw className="w-12 h-12" />} title="Tidak Ada Refund" description="Tidak ada refund pada filter ini." />
      )}

      <ConfirmDialog isOpen={approveTarget !== null} onClose={() => !actingId && setApproveTarget(null)} onConfirm={handleApprove}
        title="Setujui Refund" message={`Refund ${fmt(approveTarget?.amount)} akan disetujui (dasar valid nominal).`}
        confirmText="Setujui" cancelText="Batal" variant="primary" isLoading={actingId !== null}>
        <Textarea label="Alasan Persetujuan (opsional)" value={approveReason} onChange={(e) => setApproveReason(e.target.value)} className="w-full mt-3" />
      </ConfirmDialog>

      <ConfirmDialog isOpen={processTarget !== null} onClose={() => !actingId && setProcessTarget(null)} onConfirm={handleProcess}
        title="Proses Refund" message="Catat rekening tujuan dan referensi transfer manual."
        confirmText="Proses" cancelText="Batal" variant="primary" isLoading={actingId !== null}>
        <div className="grid grid-cols-1 gap-3 w-full mt-3">
          <Textarea label="Rekening Tujuan (Bank / No / a.n)" required value={processForm.customer_bank_info}
            onChange={(e) => { setProcessForm((f) => ({ ...f, customer_bank_info: e.target.value })); setProcessError('') }}
            error={processError} />
          <Input label="Referensi Transfer" value={processForm.transfer_reference}
            onChange={(e) => setProcessForm((f) => ({ ...f, transfer_reference: e.target.value }))} />
        </div>
      </ConfirmDialog>

      <ConfirmDialog isOpen={completeTarget !== null} onClose={() => !actingId && setCompleteTarget(null)} onConfirm={handleComplete}
        title="Selesaikan Refund" message="Lampirkan bukti transfer sebagai bukti penyelesaian."
        confirmText="Selesaikan" cancelText="Batal" variant="primary" isLoading={actingId !== null}>
        <div className="grid grid-cols-1 gap-3 w-full mt-3">
          <Input label="Referensi Transfer" value={completeRef} onChange={(e) => setCompleteRef(e.target.value)} />
          <div className="flex flex-col gap-1.5">
            <span className="text-sm font-medium text-slate-700">Bukti Transfer <span className="text-rose-500">*</span></span>
            <input type="file" aria-label="Bukti transfer refund" accept="image/png,image/jpeg,image/webp,application/pdf" onChange={(e) => setProofFile(e.target.files?.[0] ?? null)} />
          </div>
        </div>
      </ConfirmDialog>

      <ConfirmDialog isOpen={failTarget !== null} onClose={() => !actingId && setFailTarget(null)} onConfirm={handleFail}
        title="Gagalkan Refund" message="Alasan wajib; riwayat tetap tersimpan."
        confirmText="Gagalkan" cancelText="Batal" variant="danger" isLoading={actingId !== null}>
        <Textarea label="Alasan Kegagalan" required value={failReason} onChange={(e) => { setFailReason(e.target.value); setFailError('') }}
          error={failError} className="w-full mt-3" placeholder="Contoh: rekening tidak valid…" />
      </ConfirmDialog>
    </div>
  )
}
export default AdminRefundsPage