import React, { useState, useEffect } from 'react'
import { CheckCircle2, XCircle, Eye, Upload } from 'lucide-react'
import { invoiceService } from '../services/invoiceService'
import type { Payment } from '@/types/invoice'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Textarea } from '@/components/form/Textarea'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  SUBMITTED: 'warning', APPROVED: 'success', REJECTED: 'danger', PENDING: 'secondary',
}

const fmt = (n: number | undefined): string =>
  new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n ?? 0)

export const AdminPaymentsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const [payments, setPayments] = useState<Payment[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [filter, setFilter] = useState('SUBMITTED')
  const [apiError, setApiError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const [approveTarget, setApproveTarget] = useState<Payment | null>(null)
  const [rejectTarget, setRejectTarget] = useState<Payment | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectError, setRejectError] = useState('')
  const [preview, setPreview] = useState<{ payment: Payment; url: string } | null>(null)
  const [previewLoading, setPreviewLoading] = useState(false)

  const openPreview = async (p: Payment) => {
    setPreviewLoading(true)
    try {
      const url = await invoiceService.fetchProofObjectUrl(p.id)
      setPreview({ payment: p, url })
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal membuka bukti.')
    } finally {
      setPreviewLoading(false)
    }
  }

  const loadQueue = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await invoiceService.queuePayments({ status: filter || undefined, per_page: 50 })
      setPayments(res.data ?? [])
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat antrean pembayaran.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadQueue()
  }, [filter])

  const handleApprove = async () => {
    if (!approveTarget) return
    setActingId(approveTarget.id)
    try {
      await invoiceService.approvePayment(approveTarget.id)
      showSuccessToast('Pembayaran diverifikasi.')
      setApproveTarget(null)
      loadQueue()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memverifikasi pembayaran.')
    } finally {
      setActingId(null)
    }
  }

  const handleReject = async () => {
    if (!rejectTarget) return
    if (rejectReason.trim().length < 5) {
      setRejectError('Alasan penolakan minimal 5 karakter.')
      return
    }
    setActingId(rejectTarget.id)
    try {
      await invoiceService.rejectPayment(rejectTarget.id, rejectReason)
      showSuccessToast('Pembayaran ditolak; riwayat tetap tersimpan.')
      setRejectTarget(null)
      setRejectReason('')
      loadQueue()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menolak pembayaran.')
    } finally {
      setActingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Verifikasi Pembayaran</h2>
          <p className="text-sm text-slate-500 mt-1">Periksa bukti, setujui (settlement) atau tolak dengan alasan.</p>
        </div>
        <Select className="w-44" value={filter} onChange={(e) => setFilter(e.target.value)}
          options={[
            { value: 'SUBMITTED', label: 'Menunggu Verifikasi' },
            { value: 'APPROVED', label: 'Disetujui' },
            { value: 'REJECTED', label: 'Ditolak' },
            { value: '', label: 'Semua' },
          ]} />
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Data">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">{[1, 2, 3].map((i) => (
          <Card key={i} className="p-5 space-y-3"><Skeleton className="h-5 w-56" /><Skeleton className="h-12 w-full rounded-xl" /></Card>
        ))}</div>
      ) : payments.length > 0 ? (
        <div className="space-y-4">
          {payments.map((p) => (
            <Card key={p.id} className="p-5">
              <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-3">
                <div className="space-y-1.5">
                  <div className="flex items-center gap-2 flex-wrap">
                    <Badge variant={STATUS_VARIANT[p.status] ?? 'secondary'} size="sm">{p.status}</Badge>
                    <span className="font-semibold text-slate-900">{fmt(p.amount)}</span>
                    <span className="text-xs text-slate-400">{p.invoice?.invoice_number ?? `Invoice #${p.invoice_id}`}</span>
                  </div>
                  <div className="text-xs text-slate-500">{p.invoice?.booking_code ?? '-'}</div>
                  <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                    <span>Transfer: {new Date(p.payment_date).toLocaleDateString('id-ID')}</span>
                    {p.sender_name && <span>Pengirim: {p.sender_name}</span>}
                    {p.reference && <span>Ref: {p.reference}</span>}
                  </div>
                  {p.status === 'REJECTED' && p.rejection_reason && (
                    <div className="text-xs text-rose-700 bg-rose-50 border border-rose-200 rounded-lg px-2 py-1 inline-flex items-center gap-1">
                      <XCircle size={12} /> {p.rejection_reason}
                    </div>
                  )}
                </div>

                <div className="flex items-center gap-2 shrink-0">
                  <Button variant="outline" size="sm" isLoading={previewLoading} onClick={() => openPreview(p)}>
                    <Eye size={14} className="mr-1" /> Lihat Bukti
                  </Button>
                  {p.status === 'SUBMITTED' && (
                    <>
                      <Button variant="primary" size="sm" onClick={() => setApproveTarget(p)}>
                        <CheckCircle2 size={14} className="mr-1" /> Setujui
                      </Button>
                      <Button variant="danger" size="sm" onClick={() => { setRejectTarget(p); setRejectReason(''); setRejectError('') }}>
                        <XCircle size={14} className="mr-1" /> Tolak
                      </Button>
                    </>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState icon={<Upload className="w-12 h-12" />} title="Tidak Ada Pembayaran" description="Tidak ada pembayaran pada filter ini." />
      )}

      <ConfirmDialog isOpen={preview !== null} onClose={() => setPreview(null)} onConfirm={() => setPreview(null)}
        title="Bukti Transfer" message="" confirmText="Tutup" cancelText="Kembali" variant="primary">
        {preview && (
          <div className="w-full mt-3">
            {preview.payment.proof?.mime_type?.startsWith('image/') ? (
              <img src={preview.url} alt="Bukti transfer" className="max-h-80 w-auto rounded-xl border border-slate-200" />
            ) : (
              <a className="inline-flex items-center gap-2 text-sm text-primary-600 underline" href={preview.url} target="_blank" rel="noreferrer">
                Buka berkas bukti (PDF)
              </a>
            )}
          </div>
        )}
      </ConfirmDialog>

      <ConfirmDialog isOpen={approveTarget !== null} onClose={() => !actingId && setApproveTarget(null)} onConfirm={handleApprove}
        title="Setujui Pembayaran"
        message={`Settlement ${fmt(approveTarget?.amount)} terhadap invoice akan diterapkan (exact → PAID, sebagian → PARTIAL, berlebih → OVERPAID).`}
        confirmText="Setujui" cancelText="Batal" variant="primary" isLoading={actingId !== null} />

      <ConfirmDialog isOpen={rejectTarget !== null} onClose={() => !actingId && setRejectTarget(null)} onConfirm={handleReject}
        title="Tolak Pembayaran" message="Payment tetap tersimpan; alasan wajib untuk riwayat."
        confirmText="Tolak" cancelText="Batal" variant="danger" isLoading={actingId !== null}>
        <Textarea label="Alasan Penolakan" required rows={3} value={rejectReason}
          onChange={(e) => { setRejectReason(e.target.value); setRejectError('') }}
          error={rejectError} placeholder="Contoh: nominal tidak sesuai mutasi bank…" className="w-full mt-3" />
      </ConfirmDialog>
    </div>
  )
}
export default AdminPaymentsPage