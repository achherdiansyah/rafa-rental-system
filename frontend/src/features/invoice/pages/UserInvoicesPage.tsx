import React, { useState, useEffect } from 'react'
import { Receipt, Upload, CheckCircle2, XCircle, Info } from 'lucide-react'
import { invoiceService } from '../services/invoiceService'
import { bankService } from '@/features/bank/services/bankService'
import type { BankAccount, Invoice, InvoiceDetail, Payment } from '@/types/invoice'
import { canUserPay, invoiceTypeLabel } from '@/types/invoice'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary',
  ISSUED: 'secondary',
  UNPAID: 'warning',
  PARTIALLY_PAID: 'warning',
  PAID: 'success',
  OVERPAID: 'danger',
  OVERDUE: 'danger',
  CANCELLED: 'outline',
}

const PAYMENT_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  SUBMITTED: 'warning',
  APPROVED: 'success',
  REJECTED: 'danger',
  PENDING: 'secondary',
}

function fmt(n: number | undefined): string {
  return new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', minimumFractionDigits: 2 }).format(n ?? 0)
}

const CountdownTimer: React.FC<{ dueAt: string }> = ({ dueAt }) => {
  const calculateTimeLeft = () => {
    const diff = new Date(dueAt).getTime() - new Date().getTime()
    return Math.max(0, Math.floor(diff / 1000))
  }

  const [timeLeft, setTimeLeft] = useState(calculateTimeLeft())

  useEffect(() => {
    const timer = setInterval(() => {
      setTimeLeft(calculateTimeLeft())
    }, 1000)
    return () => clearInterval(timer)
  }, [dueAt])

  if (timeLeft <= 0) {
    return <span className="font-medium text-rose-600">Pembayaran telah melewati batas waktu.</span>
  }

  const h = String(Math.floor(timeLeft / 3600)).padStart(2, '0')
  const m = String(Math.floor((timeLeft % 3600) / 60)).padStart(2, '0')
  const s = String(timeLeft % 60).padStart(2, '0')

  return (
    <span className="font-mono font-bold text-rose-600 tracking-tight">
      [ {h}:{m}:{s} ] tersisa
    </span>
  )
}

export const UserInvoicesPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [invoices, setInvoices] = useState<Invoice[]>([])
  const [payments, setPayments] = useState<Record<number, Payment[]>>({})
  const [banks, setBanks] = useState<BankAccount[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [expandedId, setExpandedId] = useState<number | null>(null)
  const [uploadOpen, setUploadOpen] = useState<number | null>(null)
  const [uploading, setUploading] = useState(false)
  const [form, setForm] = useState({ amount: '', payment_date: '', bank_account_id: '', sender_name: '', reference: '' })
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})
  const [proofFile, setProofFile] = useState<File | null>(null)

  const loadAll = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const [invRes, bankRes] = await Promise.all([
        invoiceService.getInvoices({ per_page: 50 }),
        bankService.getAccounts(),
      ])
      setInvoices(invRes.data ?? [])
      setBanks(bankRes ?? [])
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat invoice.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadAll()
  }, [])

  const loadPayments = async (invoiceId: number) => {
    const list = await invoiceService.getPayments(invoiceId)
    setPayments((prev) => ({ ...prev, [invoiceId]: list ?? [] }))
  }

  const toggle = async (invoice: Invoice) => {
    if (expandedId === invoice.id) {
      setExpandedId(null)
      return
    }
    setExpandedId(invoice.id)
    setUploadOpen(null)
    if (!payments[invoice.id]) await loadPayments(invoice.id)
  }

  const openUpload = (invoice: Invoice) => {
    setUploadOpen(invoice.id)
    setForm({
      amount: String(invoice.balance_amount || ''),
      payment_date: new Date().toISOString().slice(0, 10),
      bank_account_id: '',
      sender_name: '',
      reference: '',
    })
    setFormErrors({})
    setProofFile(null)
  }

  const handleSubmitPayment = async (invoiceId: number) => {
    const errors: Record<string, string> = {}
    if (!form.amount || Number(form.amount) <= 0) errors.amount = 'Nominal harus lebih besar dari 0.'
    if (!form.payment_date) errors.payment_date = 'Tanggal transfer wajib diisi.'
    if (!form.bank_account_id) errors.bank_account_id = 'Pilih rekening tujuan pembayaran.'
    if (!proofFile) errors.proof = 'Bukti transfer wajib dilampirkan.'
    setFormErrors(errors)
    if (Object.keys(errors).length > 0) return

    setUploading(true)
    try {
      await invoiceService.submitPayment(invoiceId, {
        amount: Number(form.amount),
        payment_date: form.payment_date,
        bank_account_id: Number(form.bank_account_id),
        sender_name: form.sender_name || undefined,
        reference: form.reference || undefined,
        proof: proofFile!,
      })
      showSuccessToast('Bukti pembayaran diajukan dan menunggu verifikasi Admin.')
      setUploadOpen(null)
      await loadPayments(invoiceId)
      loadAll()
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengajukan pembayaran.')
    } finally {
      setUploading(false)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Tagihan & Pembayaran</h2>
        <p className="text-sm text-slate-500 mt-1">
          Pantau invoice, saldo terutang, riwayat pembayaran, dan ajukan bukti transfer.
        </p>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Invoice">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-3">
              <Skeleton className="h-5 w-52" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : invoices.length > 0 ? (
        <div className="space-y-4">
          {invoices.map((inv) => {
            const expanded = expandedId === inv.id
            const balance = inv.balance_amount
            const items = payments[inv.id] ?? []
            return (
              <Card key={inv.id} className="p-5">
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                  <div className="space-y-1.5" onClick={() => toggle(inv)} role="button">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-mono text-sm font-bold text-slate-900">{inv.invoice_number}</span>
                      <Badge variant={STATUS_VARIANT[inv.status] ?? 'secondary'} size="sm">{inv.status}</Badge>
                      <span className="text-xs text-slate-400">{invoiceTypeLabel[inv.invoice_type]}</span>
                    </div>
                    <div className="text-xs text-slate-500">
                      {inv.booking?.booking_code ?? '-'} • {inv.booking?.project?.project_name ?? '-'}
                    </div>
                    <div className="flex flex-wrap items-center gap-x-5 gap-y-1 text-sm">
                      <span className="text-slate-600">Total: <strong className="text-slate-900">{fmt(inv.grand_total)}</strong></span>
                      <span className="text-emerald-600">Dibayar: {fmt(inv.paid_amount)}</span>
                      <span className={balance > 0 ? 'text-rose-600' : 'text-slate-500'}>Saldo: {fmt(balance)}</span>
{inv.due_at && ['ISSUED', 'UNPAID', 'PARTIALLY_PAID', 'OVERDUE'].includes(inv.status) && (
                          <div className="flex flex-col gap-0.5 mt-1 sm:mt-0">
                            <CountdownTimer dueAt={inv.due_at} />
                            <span className="text-xs text-slate-400">
                              Jatuh tempo: {new Date(inv.due_at).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}, {new Date(inv.due_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} WIB
                            </span>
                          </div>
                        )}
                    </div>
                    {inv.status === 'OVERPAID' && (
                      <span className="inline-flex items-center gap-1 text-xs text-rose-600 bg-rose-50 border border-rose-200 rounded-lg px-2 py-0.5">
                        <Info size={12} /> Kelebihan bayar {fmt(inv.overpayment_amount)} menunggu penyelesaian manual
                      </span>
                    )}
                  </div>

                  {canUserPay(inv) && (
                    <Button variant="primary" size="sm" className="gap-1.5 shrink-0" onClick={() => openUpload(inv)}>
                      <Upload size={14} /> Bayar Sekarang
                    </Button>
                  )}
                </div>

                {/* Upload form (always visible when opened) */}
                {uploadOpen === inv.id && (
                  <div className="mt-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-3 border border-primary-200 bg-primary-50/40 rounded-xl">
                    <Input label="Nominal Transfer" required type="number" min={0} step="0.01"
                      value={form.amount} onChange={(e) => setForm((f) => ({ ...f, amount: e.target.value }))} error={formErrors.amount} />
                    <Input label="Tanggal Transfer" required type="date" value={form.payment_date}
                      max={new Date().toISOString().slice(0, 10)}
                      onChange={(e) => setForm((f) => ({ ...f, payment_date: e.target.value }))} error={formErrors.payment_date} />
                    <Select label="Rekening Tujuan" required placeholder="Pilih rekening RAFA…"
                      value={form.bank_account_id}
                      onChange={(e) => setForm((f) => ({ ...f, bank_account_id: e.target.value }))} error={formErrors.bank_account_id}
                      options={banks.map((b) => ({ value: b.id, label: `${b.bank_name} • ${b.account_number} (${b.account_name})` }))} />
                    <Input label="Nama Pengirim" value={form.sender_name}
                      onChange={(e) => setForm((f) => ({ ...f, sender_name: e.target.value }))} />
                    <Input label="Referensi / Nomor Transfer" value={form.reference}
                      onChange={(e) => setForm((f) => ({ ...f, reference: e.target.value }))} />
                    <div className="flex flex-col gap-1.5">
                      <span className="text-sm font-medium text-slate-700">Bukti Transfer (PNG/JPG/PDF) <span className="text-rose-500">*</span></span>
                      <input type="file" aria-label="Bukti transfer" accept="image/png,image/jpeg,image/webp,application/pdf"
                        onChange={(e) => setProofFile(e.target.files?.[0] ?? null)} />
                      {formErrors.proof && <p className="text-sm text-rose-500 font-medium">{formErrors.proof}</p>}
                    </div>
                    <div className="sm:col-span-2 lg:col-span-3 flex items-center justify-end gap-2">
                      <Button variant="outline" size="sm" onClick={() => setUploadOpen(null)}>Batal</Button>
                      <Button variant="primary" size="sm" isLoading={uploading} onClick={() => handleSubmitPayment(inv.id)}>
                        <Upload size={14} className="mr-1" /> Ajukan Bukti
                      </Button>
                    </div>
                  </div>
                )}

                {expanded && (
                  <div className="mt-4 border-t border-slate-100 pt-3 space-y-3">
                    {/* Line items */}
                    <div className="text-xs text-slate-600 space-y-1">
                      {inv.details.map((d: InvoiceDetail) => (
                        <div key={d.id} className="flex justify-between gap-3 bg-slate-50 rounded-lg px-3 py-1.5">
                          <span>{d.description}</span>
                          <span className="text-slate-900 font-medium">{fmt(d.subtotal)}</span>
                        </div>
                      ))}
                    </div>

                    {/* Payment history */}
                    <div>
                      <p className="text-xs font-semibold text-slate-700 mb-1.5">Riwayat Pembayaran</p>
                      {items.length > 0 ? (
                        <div className="space-y-1.5">
                          {items.map((p: Payment) => (
                            <div key={p.id} className="flex flex-col sm:flex-row sm:items-center justify-between gap-1 text-xs bg-slate-50 rounded-lg px-3 py-2">
                              <div className="flex items-center gap-2 flex-wrap">
                                <Badge variant={PAYMENT_VARIANT[p.status] ?? 'secondary'} size="sm">{p.status}</Badge>
                                <span className="font-medium text-slate-900">{fmt(p.amount)}</span>
                                <span className="text-slate-400">{new Date(p.payment_date).toLocaleDateString('id-ID')}</span>
                                {p.sender_name && <span className="text-slate-500">{p.sender_name}</span>}
                                {p.reference && <span className="text-slate-400">ref: {p.reference}</span>}
                              </div>
                              {p.status === 'APPROVED' ? (
                                <span className="inline-flex items-center gap-1 text-emerald-600"><CheckCircle2 size={12} /> Terverifikasi</span>
                              ) : p.status === 'REJECTED' ? (
                                <span className="inline-flex items-center gap-1 text-rose-600"><XCircle size={12} /> {p.rejection_reason ?? 'Ditolak'}</span>
                              ) : (
                                <span className="text-slate-400">Menunggu verifikasi Admin</span>
                              )}
                            </div>
                          ))}
                        </div>
                      ) : (
                        <p className="text-xs text-slate-400">Belum ada pembayaran.</p>
                      )}
                    </div>
                  </div>
                )}
              </Card>
            )
          })}
        </div>
      ) : (
        <EmptyState icon={<Receipt className="w-12 h-12" />} title="Belum Ada Invoice"
          description="Invoice diterbitkan setelah rental selesai dan diverifikasi." />
      )}
    </div>
  )
}
export default UserInvoicesPage