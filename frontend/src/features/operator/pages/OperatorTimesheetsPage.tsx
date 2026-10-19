import React, { useEffect, useState } from 'react'
import { Clock, Upload, Send, FileImage, History } from 'lucide-react'
import { operatorService } from '../services/operatorService'
import type { OperatorAssignment } from '../services/operatorService'
import { timesheetService } from '@/features/timesheet/services/timesheetService'
import type { Timesheet } from '@/types/timesheet'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Textarea } from '@/components/form/Textarea'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger'> = {
  DRAFT: 'secondary',
  SUBMITTED: 'warning',
  APPROVED: 'success',
  REJECTED: 'danger',
}

export const OperatorTimesheetsPage: React.FC<{ history?: boolean }> = ({ history = false }) => {
  const { success, error: showError } = useToast()
  const [assignments, setAssignments] = useState<OperatorAssignment[]>([])
  const [timesheets, setTimesheets] = useState<Timesheet[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)

  const [form, setForm] = useState({
    rental_detail_id: '',
    report_date: new Date().toISOString().slice(0, 10),
    start_time: '',
    end_time: '',
    notes: '',
  })
  const [file, setFile] = useState<File | null>(null)

  const load = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const [a, t] = await Promise.all([
        operatorService.getAssignments(),
        timesheetService.getTimesheets({ per_page: 50 }),
      ])
      setAssignments(a.data ?? [])
      setTimesheets(t.data ?? [])
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat data Timesheet.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const ongoing = assignments.filter((a) => a.rental_status === 'ONGOING' && a.status === 'ACTIVE')

  const pickFile = (picked: File | null) => {
    if (!picked) {
      setFile(null)
      return
    }
    const allowed = ['image/jpeg', 'image/png', 'image/webp']
    if (!allowed.includes(picked.type)) {
      showError('Tipe file tidak diizinkan. Gunakan JPG, PNG, atau WebP.')
      setFile(null)
      return
    }
    if (picked.size > 5 * 1024 * 1024) {
      showError('Ukuran file melebihi 5 MB.')
      setFile(null)
      return
    }
    setFile(picked)
  }

  const handleCreate = async () => {
    if (!form.rental_detail_id) return showError('Pilih unit rental ONGOING.')
    if (!form.start_time || !form.end_time) return showError('Jam mulai dan selesai wajib diisi.')
    if (!file) return showError('Foto formulir Timesheet kertas wajib diunggah.')

    setIsSubmitting(true)
    try {
      const created = await timesheetService.create({
        rental_detail_id: Number(form.rental_detail_id),
        report_date: form.report_date,
        start_time: form.start_time,
        end_time: form.end_time,
        break_minutes: 0,
        standby_hours: 0,
        breakdown_hours: 0,
        notes: form.notes || undefined,
      })
      if (!created.data) throw new Error('Respons Timesheet tidak valid.')
      await timesheetService.uploadPaper(created.data.id, file)
      await timesheetService.submit(created.data.id)
      success('Timesheet berhasil dikirim untuk validasi Admin.')
      setForm({ rental_detail_id: '', report_date: new Date().toISOString().slice(0, 10), start_time: '', end_time: '', notes: '' })
      setFile(null)
      load()
    } catch (err: any) {
      showError(err?.message || 'Gagal mengirim Timesheet.')
      load()
    } finally {
      setIsSubmitting(false)
    }
  }

  const uploadAndSubmit = async (t: Timesheet) => {
    if (!file) return showError('Pilih foto formulir kertas terlebih dahulu.')
    setIsSubmitting(true)
    try {
      await timesheetService.uploadPaper(t.id, file)
      await timesheetService.submit(t.id)
      success('Timesheet berhasil diajukan ulang.')
      setFile(null)
      load()
    } catch (err: any) {
      showError(err?.message || 'Gagal mengajukan Timesheet.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const openPaper = async (id: number) => {
    try {
      const blob = await timesheetService.getPaperPhoto(id)
      window.open(URL.createObjectURL(blob), '_blank', 'noopener,noreferrer')
    } catch (err: any) {
      showError(err?.message || 'Gagal membuka foto Timesheet.')
    }
  }

  const visible = history ? timesheets.filter((t) => t.status !== 'DRAFT') : timesheets

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          {history ? <History className="text-primary-600" size={24} /> : <Clock className="text-primary-600" size={24} />}
          {history ? 'Riwayat Timesheet' : 'Timesheet Harian'}
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          {history ? 'Status dan catatan validasi Timesheet Anda.' : 'Input jam kerja aktual dan unggah foto formulir kertas.'}
        </p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat">{apiError}</Alert>}

      {!history && (
        <Card className="p-5 space-y-3">
          <h3 className="font-semibold text-slate-900">Input Timesheet Baru</h3>
          {ongoing.length === 0 ? (
            <Alert variant="warning" title="Tidak Ada Rental ONGOING">Timesheet hanya dapat dibuat saat penugasan rental berstatus ONGOING.</Alert>
          ) : (
            <>
              <Select
                label="Unit Rental ONGOING *"
                value={form.rental_detail_id}
                onChange={(e) => setForm((p) => ({ ...p, rental_detail_id: e.target.value }))}
              >
                <option value="">-- Pilih Unit --</option>
                {ongoing.map((a) => (
                  <option key={a.id} value={a.rental_detail_id}>
                    {a.unit?.serial_number ?? `Unit #${a.rental_detail_id}`} — {a.booking_code} — {a.project_location?.project_name}
                  </option>
                ))}
              </Select>
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <Input label="Tanggal Kerja *" type="date" max={new Date().toISOString().slice(0, 10)} value={form.report_date} onChange={(e) => setForm((p) => ({ ...p, report_date: e.target.value }))} />
                <Input label="Jam Mulai *" type="time" value={form.start_time} onChange={(e) => setForm((p) => ({ ...p, start_time: e.target.value }))} />
                <Input label="Jam Selesai *" type="time" value={form.end_time} onChange={(e) => setForm((p) => ({ ...p, end_time: e.target.value }))} />
              </div>
              <Textarea label="Catatan" rows={2} value={form.notes} onChange={(e) => setForm((p) => ({ ...p, notes: e.target.value }))} />
              <div>
                <label className="block text-sm font-medium text-slate-700 mb-1.5">Foto Formulir Kertas *</label>
                <input
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  onChange={(e) => pickFile(e.target.files?.[0] ?? null)}
                  className="block w-full text-sm text-slate-600 file:mr-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-primary-700"
                />
                <p className="mt-1 text-xs text-slate-400">JPG/PNG/WebP maksimal 5 MB, disimpan privat.</p>
              </div>
              <div className="flex justify-end">
                <Button onClick={handleCreate} isLoading={isSubmitting} className="gap-2"><Send size={15} /> Kirim untuk Validasi</Button>
              </div>
            </>
          )}
        </Card>
      )}

      {history && (
        <Card className="p-4">
          <label className="block text-sm font-medium text-slate-700 mb-1.5">Pilih foto untuk Timesheet yang ditolak / draft</label>
          <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => pickFile(e.target.files?.[0] ?? null)} className="block w-full text-sm" />
        </Card>
      )}

      {isLoading ? (
        <Card className="p-5"><Skeleton className="h-24 w-full" /></Card>
      ) : visible.length === 0 ? (
        <EmptyState icon={<Clock className="w-12 h-12" />} title="Belum Ada Timesheet" description="Timesheet yang Anda input akan muncul di sini." />
      ) : (
        <div className="space-y-3">
          {visible.map((t) => (
            <Card key={t.id} className="p-4 space-y-2">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-mono text-sm font-bold text-slate-900">#{t.id}</span>
                <Badge variant={STATUS_VARIANT[t.status] ?? 'secondary'} size="sm">{t.status}</Badge>
                <span className="text-xs text-slate-500">{t.report_date} — {t.rental?.unit_serial ?? '-'} — {t.rental?.project_name ?? '-'}</span>
              </div>
              <p className="text-xs text-slate-500">
                {t.start_time ?? t.start_hm} → {t.end_time ?? t.end_hm} • {t.total_work_hours} jam
              </p>
              {t.rejection_reason && <p className="text-xs text-rose-600">Alasan: {t.rejection_reason}</p>}
              {t.status === 'APPROVED' && t.invoice && (
                <p className="text-xs text-emerald-700">
                  Daily Work Invoice: {t.invoice.invoice_number} — Rp {Number(t.invoice.grand_total).toLocaleString('id-ID')}
                </p>
              )}
              <div className="flex flex-wrap gap-2">
                {t.paper_form && (
                  <Button variant="outline" size="sm" className="gap-1.5" onClick={() => openPaper(t.id)}>
                    <FileImage size={14} /> Lihat Foto Kertas
                  </Button>
                )}
                {(t.status === 'DRAFT' || t.status === 'REJECTED') && (
                  <>
                    <label className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-slate-700 cursor-pointer hover:bg-slate-50">
                      <Upload size={13} /> Pilih Foto
                      <input type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(e) => pickFile(e.target.files?.[0] ?? null)} />
                    </label>
                    <Button size="sm" className="gap-1.5" disabled={!file} isLoading={isSubmitting} onClick={() => uploadAndSubmit(t)}>
                      <Send size={13} /> Unggah & Submit
                    </Button>
                  </>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

export default OperatorTimesheetsPage

