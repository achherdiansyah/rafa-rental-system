import React, { useState, useEffect } from 'react'
import { Clock, PenLine, Send, FileSignature, History, CheckCircle2, XCircle, Loader2 } from 'lucide-react'
import { timesheetService } from '../services/timesheetService'
import { rentalService } from '@/features/rental/services/rentalService'
import type { Timesheet, TimesheetRevision } from '@/types/timesheet'
import type { Rental } from '@/types/rental'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Textarea } from '@/components/form/Textarea'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary',
  SUBMITTED: 'warning',
  APPROVED: 'success',
  REJECTED: 'danger',
}

const HM_PATTERN = /^\d+(\.\d{1,2})?$/

export const UserTimesheetsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [timesheets, setTimesheets] = useState<Timesheet[]>([])
  const [rentals, setRentals] = useState<Rental[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const [formOpen, setFormOpen] = useState(false)
  const [form, setForm] = useState({
    rental_detail_id: '',
    report_date: new Date().toISOString().slice(0, 10),
    start_hm: '',
    end_hm: '',
    break_minutes: '0',
    standby_hours: '0',
    breakdown_hours: '0',
    operator_name: '',
    notes: '',
  })
  const [formErrors, setFormErrors] = useState<Record<string, string>>({})

  const [pendingSubmit, setPendingSubmit] = useState<Timesheet | null>(null)
  const [signingId, setSigningId] = useState<number | null>(null)
  const [expandedRevision, setExpandedRevision] = useState<number | null>(null)
  const [revisions, setRevisions] = useState<Record<number, TimesheetRevision[]>>({})

  const ongoingDetails = rentals
    .filter((r) => r.status === 'ONGOING')
    .flatMap((r) =>
      r.details.map((d) => ({
        value: d.id,
        label: `${r.booking?.booking_code ?? `Rental #${r.id}`} • ${d.unit?.serial_number ?? 'unit'} (${r.booking?.project_location?.project_name ?? ''})`,
      })),
    )

  const loadAll = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const [tsRes, rentRes] = await Promise.all([
        timesheetService.getTimesheets({ per_page: 50 }),
        rentalService.getRentals({ per_page: 50 }),
      ])
      if (tsRes.success && tsRes.data) setTimesheets(tsRes.data)
      if (rentRes.success && rentRes.data) setRentals(rentRes.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat data timesheet.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadAll()
  }, [])

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }))
    setFormErrors((prev) => ({ ...prev, [e.target.name]: '' }))
  }

  const validateForm = (): boolean => {
    const errors: Record<string, string> = {}
    if (!form.rental_detail_id) errors.rental_detail_id = 'Pilih unit rental yang sedang berjalan.'
    if (!form.report_date) errors.report_date = 'Tanggal laporan wajib diisi.'
    if (!HM_PATTERN.test(form.start_hm) || !HM_PATTERN.test(form.end_hm)) {
      errors.start_hm = 'Jam meter harus angka (mis. 08.30).'
      errors.end_hm = 'Jam meter harus angka (mis. 16.30).'
    } else if (Number(form.end_hm) <= Number(form.start_hm)) {
      errors.end_hm = 'Jam akhir harus lebih besar dari jam awal.'
    }
    setFormErrors(errors)
    return Object.keys(errors).length === 0
  }

  const handleCreate = async () => {
    if (!validateForm()) return
    setActingId(-1)
    try {
      const res = await timesheetService.create({
        rental_detail_id: Number(form.rental_detail_id),
        report_date: form.report_date,
        start_hm: Number(form.start_hm),
        end_hm: Number(form.end_hm),
        break_minutes: Number(form.break_minutes || 0),
        standby_hours: Number(form.standby_hours || 0),
        breakdown_hours: Number(form.breakdown_hours || 0),
        operator_name: form.operator_name || undefined,
        notes: form.notes || undefined,
      })
      if (res.success && res.data) {
        showSuccessToast(`Timesheet ${res.data.rental?.unit_serial ?? ''} • ${res.data.total_work_hours} jam tersimpan.`)
        setFormOpen(false)
        setForm({
          rental_detail_id: '',
          report_date: new Date().toISOString().slice(0, 10),
          start_hm: '',
          end_hm: '',
          break_minutes: '0',
          standby_hours: '0',
          breakdown_hours: '0',
          operator_name: '',
          notes: '',
        })
        loadAll()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyimpan timesheet.')
    } finally {
      setActingId(null)
    }
  }

  const handleSubmit = async () => {
    if (!pendingSubmit) return
    setActingId(pendingSubmit.id)
    try {
      const res = await timesheetService.submit(pendingSubmit.id)
      if (res.success && res.data) {
        showSuccessToast(`Timesheet ${res.data.id} diajukan untuk validasi Admin.`)
        loadAll()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengajukan timesheet.')
    } finally {
      setActingId(null)
      setPendingSubmit(null)
    }
  }

  const handleSign = async (timesheet: Timesheet, file: File) => {
    setSigningId(timesheet.id)
    try {
      const res = await timesheetService.sign(timesheet.id, file)
      if (res.success && res.data) {
        showSuccessToast('Tanda tangan berhasil dilampirkan.')
        loadAll()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengunggah tanda tangan.')
    } finally {
      setSigningId(null)
    }
  }

  const toggleRevisions = async (id: number) => {
    if (expandedRevision === id) {
      setExpandedRevision(null)
      return
    }
    setExpandedRevision(id)
    if (!revisions[id]) {
      const res = await timesheetService.getRevisions(id)
      if (res.success && res.data) setRevisions((prev) => ({ ...prev, [id]: res.data ?? [] }))
    }
  }

  const canSubmit = (t: Timesheet) => t.status === 'DRAFT' || t.status === 'REJECTED'
  const canSign = (t: Timesheet) => t.status === 'DRAFT' || t.status === 'SUBMITTED'

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Timesheet Harian</h2>
          <p className="text-sm text-slate-500 mt-1">
            Catat jam kerja armada per hari, ajukan validasi, dan lampirkan tanda tangan.
          </p>
        </div>
        <Button variant="primary" size="sm" className="gap-1.5 shrink-0" onClick={() => setFormOpen((v) => !v)}>
          <PenLine size={15} />
          Catat Timesheet
        </Button>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Data">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-3">
              <Skeleton className="h-5 w-40" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : (
        <>
          {formOpen && (
            <Card className="p-5">
              <div className="flex items-center gap-2 mb-4">
                <Clock size={16} className="text-primary-600" />
                <h3 className="font-semibold text-slate-900">Form Pencatatan Timesheet</h3>
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <Select
                  label="Unit Rental (ONGOING)"
                  required
                  value={form.rental_detail_id}
                  onChange={handleChange}
                  name="rental_detail_id"
                  options={ongoingDetails}
                  placeholder="Pilih unit…"
                  error={formErrors.rental_detail_id}
                />
                <Input
                  label="Tanggal Laporan"
                  required
                  type="date"
                  name="report_date"
                  value={form.report_date}
                  max={new Date().toISOString().slice(0, 10)}
                  onChange={handleChange}
                  error={formErrors.report_date}
                />
                <Input
                  label="Jam Mulai (HM)"
                  required
                  placeholder="08.30"
                  name="start_hm"
                  value={form.start_hm}
                  onChange={handleChange}
                  error={formErrors.start_hm}
                />
                <Input
                  label="Jam Akhir (HM)"
                  required
                  placeholder="16.30"
                  name="end_hm"
                  value={form.end_hm}
                  onChange={handleChange}
                  error={formErrors.end_hm}
                />
                <Input
                  label="Break (menit)"
                  type="number"
                  min={0}
                  name="break_minutes"
                  value={form.break_minutes}
                  onChange={handleChange}
                />
                <Input
                  label="Standby (jam)"
                  type="number"
                  min={0}
                  step="0.5"
                  name="standby_hours"
                  value={form.standby_hours}
                  onChange={handleChange}
                />
                <Input
                  label="Breakdown (jam)"
                  type="number"
                  min={0}
                  step="0.5"
                  name="breakdown_hours"
                  value={form.breakdown_hours}
                  onChange={handleChange}
                />
                <Input
                  label="Nama Operator"
                  name="operator_name"
                  value={form.operator_name}
                  onChange={handleChange}
                />
                <Textarea
                  label="Catatan"
                  name="notes"
                  value={form.notes}
                  onChange={handleChange}
                  className="sm:col-span-2 lg:col-span-3"
                />
              </div>
              <div className="flex items-center justify-end gap-3 mt-4">
                <Button variant="outline" size="sm" onClick={() => setFormOpen(false)}>
                  Batal
                </Button>
                <Button variant="primary" size="sm" onClick={handleCreate} isLoading={actingId === -1}>
                  Simpan Timesheet
                </Button>
              </div>
            </Card>
          )}

          {timesheets.length > 0 ? (
            <div className="space-y-4">
              {timesheets.map((ts) => {
                const hasSignature = !!ts.signature
                const isExpanded = expandedRevision === ts.id
                return (
                  <Card key={ts.id} className="p-5">
                    <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                      <div className="space-y-2">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className="font-mono text-sm font-bold text-slate-900">#{ts.id}</span>
                          <span className="text-sm text-slate-700">
                            {ts.report_date} • {ts.rental?.booking_code ?? '-'}
                          </span>
                          <Badge variant={STATUS_VARIANT[ts.status] ?? 'secondary'} size="sm">
                            {ts.status}
                          </Badge>
                        </div>

                        <div className="text-sm text-slate-600">
                          <span className="font-semibold text-slate-900">{ts.rental?.unit_serial ?? '-'}</span>
                          <span className="text-slate-400 ml-1">
                            {ts.rental?.unit_plate ?? ''} • {ts.rental?.project_name ?? ''}
                          </span>
                        </div>

                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                          <span>Jam: {ts.start_hm} → {ts.end_hm}</span>
                          <span>Break: {ts.break_minutes} mnt</span>
                          <span className="font-semibold text-slate-900">
                            Kerja: {ts.total_work_hours} jam
                          </span>
                          <span>Standby: {ts.standby_hours}</span>
                          <span>Breakdown: {ts.breakdown_hours}</span>
                          {ts.operator_name && <span>Operator: {ts.operator_name}</span>}
                        </div>

                        {ts.notes && <p className="text-xs text-slate-500 italic">"{ts.notes}"</p>}

                        <div className="flex items-center gap-4">
                          <span className={`inline-flex items-center gap-1 text-xs ${hasSignature ? 'text-emerald-600' : 'text-slate-400'}`}>
                            {hasSignature ? <CheckCircle2 size={13} /> : <XCircle size={13} />}
                            {hasSignature ? 'Tanda tangan terlampir' : 'Belum ada tanda tangan'}
                          </span>
                          <button
                            type="button"
                            onClick={() => toggleRevisions(ts.id)}
                            className="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline"
                          >
                            <History size={13} />
                            Riwayat revisi
                          </button>
                        </div>
                      </div>

                      <div className="flex flex-wrap items-center gap-2 shrink-0">
                        {canSubmit(ts) && (
                          <Button
                            variant="primary"
                            size="sm"
                            isLoading={actingId === ts.id}
                            onClick={() => setPendingSubmit(ts)}
                          >
                            <Send size={14} className="mr-1" />
                            {ts.status === 'REJECTED' ? 'Ajukan Ulang' : 'Ajukan Validasi'}
                          </Button>
                        )}
                        {canSign(ts) && (
                          <label className="inline-flex items-center gap-1 text-xs font-medium text-primary-700 bg-primary-50 border border-primary-200 rounded-lg px-3 py-2 cursor-pointer hover:bg-primary-100">
                            {signingId === ts.id ? (
                              <Loader2 size={14} className="animate-spin" />
                            ) : (
                              <FileSignature size={14} />
                            )}
                            {signingId === ts.id ? 'Mengunggah…' : 'Tanda Tangan'}
                            <input
                              type="file"
                              accept="image/png,image/jpeg,image/webp"
                              className="hidden"
                              disabled={signingId === ts.id}
                              onChange={(e) => {
                                const file = e.target.files?.[0]
                                if (file) {
                                  handleSign(ts, file)
                                }
                                e.target.value = ''
                              }}
                            />
                          </label>
                        )}
                      </div>
                    </div>

                    {isExpanded && (
                      <div className="mt-4 border-t border-slate-100 pt-3 space-y-2">
                        {revisions[ts.id]?.length ? (
                          revisions[ts.id].map((rev) => (
                            <div key={rev.id} className="text-xs bg-slate-50 rounded-lg px-3 py-2">
                              <span className="font-semibold text-slate-900">v{rev.version}</span>{' '}
                              <span className="text-slate-500">
                                {new Date(rev.created_at).toLocaleString('id-ID')} — {rev.revised_by_name ?? 'Admin'}
                              </span>
                              <span className="block text-slate-600 mt-0.5">
                                {rev.old_start_hm} → {rev.old_end_hm}: {rev.revision_reason}
                              </span>
                            </div>
                          ))
                        ) : (
                          <p className="text-xs text-slate-400">Belum ada revisi.</p>
                        )}
                      </div>
                    )}
                  </Card>
                )
              })}
            </div>
          ) : (
            <EmptyState
              icon={<Clock className="w-12 h-12" />}
              title="Belum Ada Timesheet"
              description="Mulai catat jam kerja harian saat rental Anda berstatus ONGOING."
            />
          )}
        </>
      )}

      <ConfirmDialog
        isOpen={pendingSubmit !== null}
        onClose={() => !actingId && setPendingSubmit(null)}
        onConfirm={handleSubmit}
        title="Ajukan Validasi Timesheet"
        message={`Timesheet #${pendingSubmit?.id ?? ''} akan diajukan ke Admin untuk divalidasi (catatan terkunci dari edit).`}
        confirmText="Ajukan"
        cancelText="Batal"
        variant="primary"
        isLoading={actingId !== null}
      />
    </div>
  )
}
export default UserTimesheetsPage