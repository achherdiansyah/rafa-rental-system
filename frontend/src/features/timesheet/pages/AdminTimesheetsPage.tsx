import React, { useState, useEffect } from 'react'
import { Clock, CheckCircle2, XCircle, PenLine, History, Search, Plus } from 'lucide-react'
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
import { Modal } from '@/components/ui/Modal'
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

type FilterStatus = '' | 'SUBMITTED' | 'APPROVED' | 'REJECTED' | 'DRAFT'

interface RejectTarget {
  timesheet: Timesheet
  reason: string
}

interface ReviseTarget {
  timesheet: Timesheet
  reason: string
  start_hm: string
  end_hm: string
  break_minutes: string
  standby_hours: string
  breakdown_hours: string
}

export const AdminTimesheetsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [timesheets, setTimesheets] = useState<Timesheet[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [filter, setFilter] = useState<FilterStatus>('')
  const [actingId, setActingId] = useState<number | null>(null)
  const [apiError, setApiError] = useState<string | null>(null)

  const [approveTarget, setApproveTarget] = useState<Timesheet | null>(null)
  const [rejectTarget, setRejectTarget] = useState<RejectTarget | null>(null)
  const [reviseTarget, setReviseTarget] = useState<ReviseTarget | null>(null)
  const [rejectError, setRejectError] = useState<string>('')
  const [reviseErrors, setReviseErrors] = useState<Record<string, string>>({})

  const [expandedRevision, setExpandedRevision] = useState<number | null>(null)
  const [revisions, setRevisions] = useState<Record<number, TimesheetRevision[]>>({})

  // Input Timesheet (admin records the field/operator daily report)
  const [createOpen, setCreateOpen] = useState(false)
  const [creating, setCreating] = useState(false)
  const [rentals, setRentals] = useState<Rental[]>([])
  const [createForm, setCreateForm] = useState({
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
  const [createErrors, setCreateErrors] = useState<Record<string, string>>({})

  const ongoingDetails = rentals
    .filter((r) => r.status === 'ONGOING')
    .flatMap((r) =>
      (r.details ?? []).map((d) => ({
        value: String(d.id),
        label: `${r.booking?.booking_code ?? `Rental #${r.id}`} · ${d.unit?.serial_number ?? 'unit'} · ${r.booking?.project_location?.project_name ?? ''}`,
      })),
    )

  const openCreate = async () => {
    setCreateErrors({})
    setCreateForm((prev) => ({ ...prev, report_date: new Date().toISOString().slice(0, 10) }))
    setCreateOpen(true)
    try {
      const res = await rentalService.getRentals({ per_page: 50 })
      if (res.success && res.data) setRentals(res.data)
    } catch {
      // Rental list stays empty; the dropdown will show no selectable units
    }
  }

  const handleCreate = async () => {
    const errors: Record<string, string> = {}
    if (!createForm.rental_detail_id) errors.rental_detail_id = 'Pilih unit rental ONGOING.'
    if (!createForm.report_date) errors.report_date = 'Tanggal wajib diisi.'
    if (!createForm.start_hm || !createForm.end_hm || Number(createForm.end_hm) <= Number(createForm.start_hm)) {
      errors.end_hm = 'Jam akhir harus lebih besar dari jam awal.'
    }
    setCreateErrors(errors)
    if (Object.keys(errors).length > 0) return

    setCreating(true)
      try {
        const res = await timesheetService.create({
          rental_detail_id: Number(createForm.rental_detail_id),
          report_date: createForm.report_date,
          start_hm: Number(createForm.start_hm),
          end_hm: Number(createForm.end_hm),
          break_minutes: Number(createForm.break_minutes || 0),
          standby_hours: Number(createForm.standby_hours || 0),
          breakdown_hours: Number(createForm.breakdown_hours || 0),
          operator_name: createForm.operator_name || undefined,
          notes: createForm.notes || undefined,
        })
        if (!res.success || !res.data) {
          throw new Error('Respons tidak valid dari server.')
        }
        showSuccessToast(`Timesheet #${res.data.id} berhasil dicatat & Tagihan Harian diterbitkan.`)
        setCreateOpen(false)
        setCreateForm((prev) => ({ ...prev, rental_detail_id: '', start_hm: '', end_hm: '', operator_name: '', notes: '' }))
        loadTimesheets()
      } catch (err: any) {
        showErrorToast(err?.message || 'Gagal menginput timesheet.')
      } finally {
        setCreating(false)
      }
  }

  const loadTimesheets = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await timesheetService.getTimesheets({ status: filter || undefined, per_page: 50 })
      if (res.success && res.data) setTimesheets(res.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat timesheet.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadTimesheets()
  }, [filter])

  const handleApprove = async () => {
    if (!approveTarget) return
    setActingId(approveTarget.id)
    try {
      const res = await timesheetService.approve(approveTarget.id)
      if (res.success && res.data) {
        showSuccessToast(`Timesheet #${res.data.id} disetujui.`)
        loadTimesheets()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyetujui timesheet.')
    } finally {
      setActingId(null)
      setApproveTarget(null)
    }
  }

  const handleReject = async () => {
    if (!rejectTarget) return
    if (rejectTarget.reason.trim().length < 5) {
      setRejectError('Alasan penolakan minimal 5 karakter.')
      return
    }
    setActingId(rejectTarget.timesheet.id)
    try {
      const res = await timesheetService.reject(rejectTarget.timesheet.id, rejectTarget.reason)
      if (res.success && res.data) {
        showSuccessToast(`Timesheet #${res.data.id} ditolak; perlu koreksi.`)
        loadTimesheets()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menolak timesheet.')
    } finally {
      setActingId(null)
      setRejectTarget(null)
    }
  }

  const openRevise = (t: Timesheet) => {
    setReviseTarget({
      timesheet: t,
      reason: '',
      start_hm: String(t.start_hm),
      end_hm: String(t.end_hm),
      break_minutes: String(t.break_minutes),
      standby_hours: String(t.standby_hours),
      breakdown_hours: String(t.breakdown_hours),
    })
    setReviseErrors({})
  }

  const handleRevise = async () => {
    if (!reviseTarget) return
    const errors: Record<string, string> = {}
    if (reviseTarget.reason.trim().length < 5) errors.reason = 'Alasan koreksi minimal 5 karakter.'
    if (Number(reviseTarget.end_hm) <= Number(reviseTarget.start_hm)) errors.end_hm = 'Jam akhir harus lebih besar dari jam awal.'
    setReviseErrors(errors)
    if (Object.keys(errors).length > 0) return

    setActingId(reviseTarget.timesheet.id)
    try {
      const res = await timesheetService.revise(reviseTarget.timesheet.id, {
        start_hm: Number(reviseTarget.start_hm),
        end_hm: Number(reviseTarget.end_hm),
        break_minutes: Number(reviseTarget.break_minutes || 0),
        standby_hours: Number(reviseTarget.standby_hours || 0),
        breakdown_hours: Number(reviseTarget.breakdown_hours || 0),
        reason: reviseTarget.reason,
      })
      if (res.success && res.data) {
        showSuccessToast(`Timesheet #${res.data.id} dikoreksi; kembali menunggu validasi.`)
        loadTimesheets()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengoreksi timesheet.')
    } finally {
      setActingId(null)
      setReviseTarget(null)
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

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Validasi Timesheet</h2>
          <p className="text-sm text-slate-500 mt-1">Setujui, tolak, atau koreksi timesheet harian dari unit rental.</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="primary" size="sm" className="gap-1.5 shrink-0" onClick={openCreate} leftIcon={<Plus size={14} />}>
            Input Timesheet
          </Button>
          <Search size={15} className="text-slate-400" />
          <Select
            value={filter}
            onChange={(e) => setFilter(e.target.value as FilterStatus)}
            className="w-44"
            options={[
              { value: 'SUBMITTED', label: 'Menunggu Validasi' },
              { value: 'APPROVED', label: 'Disetujui' },
              { value: 'REJECTED', label: 'Ditolak' },
              { value: 'DRAFT', label: 'Draft' },
              { value: '', label: 'Semua Status' },
            ]}
          />
        </div>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Data">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-5 space-y-3">
              <Skeleton className="h-5 w-44" />
              <Skeleton className="h-12 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : timesheets.length > 0 ? (
        <div className="space-y-4">
          {timesheets.map((ts) => {
            const isExpanded = expandedRevision === ts.id
            return (
              <Card key={ts.id} className="p-5">
                <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-3">
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
                      <span className="font-semibold text-slate-900">Kerja: {ts.total_work_hours} jam</span>
                      <span>Standby: {ts.standby_hours}</span>
                      <span>Breakdown: {ts.breakdown_hours}</span>
                      {ts.operator_name && <span>Operator: {ts.operator_name}</span>}
                      {ts.signature && <span className="text-emerald-600">Tanda tangan ✓</span>}
                    </div>

                    {ts.notes && <p className="text-xs text-slate-500 italic">"{ts.notes}"</p>}

                    <button
                      type="button"
                      onClick={() => toggleRevisions(ts.id)}
                      className="inline-flex items-center gap-1 text-xs text-primary-600 hover:underline"
                    >
                      <History size={13} />
                      Riwayat revisi
                    </button>
                  </div>

                  <div className="flex flex-wrap items-center gap-2 shrink-0">
                    {ts.status === 'SUBMITTED' && (
                      <>
                        <Button variant="primary" size="sm" onClick={() => setApproveTarget(ts)}>
                          <CheckCircle2 size={14} className="mr-1" />
                          Setujui
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => setRejectTarget({ timesheet: ts, reason: '' })}>
                          <XCircle size={14} className="mr-1" />
                          Tolak
                        </Button>
                      </>
                    )}
                    {(ts.status === 'APPROVED' || ts.status === 'REJECTED') && (
                      <Button variant="outline" size="sm" onClick={() => openRevise(ts)}>
                        <PenLine size={14} className="mr-1" />
                        Koreksi
                      </Button>
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
          title="Tidak Ada Timesheet"
          description="Tidak ada timesheet pada filter status saat ini."
        />
      )}

      <Modal
        isOpen={createOpen}
        onClose={() => !creating && setCreateOpen(false)}
        title="Input Timesheet Harian"
        size="lg"
      >
        <div className="space-y-4">
          <p className="text-xs text-slate-500">
            Catat pekerjaan aktual berdasarkan laporan operator. Timesheet langsung tersimpan sebagai APPROVED dan Tagihan Harian otomatis diterbitkan.
          </p>
          <Select
            label="Unit Rental (ONGOING) *"
            required
            name="rental_detail_id"
            value={createForm.rental_detail_id}
            onChange={(e) => setCreateForm((p) => ({ ...p, rental_detail_id: e.target.value }))}
            options={ongoingDetails}
            placeholder="Pilih unit…"
            error={createErrors.rental_detail_id}
          />
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Tanggal Laporan *"
              type="date"
              value={createForm.report_date}
              max={new Date().toISOString().slice(0, 10)}
              onChange={(e) => setCreateForm((p) => ({ ...p, report_date: e.target.value }))}
              error={createErrors.report_date}
            />
            <Input
              label="Nama Operator"
              placeholder="Dari laporan operator lapangan"
              value={createForm.operator_name}
              onChange={(e) => setCreateForm((p) => ({ ...p, operator_name: e.target.value }))}
            />
            <Input
              label="Jam Mulai (HM) *"
              type="number"
              step="0.01"
              value={createForm.start_hm}
              onChange={(e) => setCreateForm((p) => ({ ...p, start_hm: e.target.value }))}
              error={createErrors.start_hm}
            />
            <Input
              label="Jam Akhir (HM) *"
              type="number"
              step="0.01"
              value={createForm.end_hm}
              onChange={(e) => setCreateForm((p) => ({ ...p, end_hm: e.target.value }))}
              error={createErrors.end_hm}
            />
            <Input
              label="Break (menit)"
              type="number"
              min={0}
              value={createForm.break_minutes}
              onChange={(e) => setCreateForm((p) => ({ ...p, break_minutes: e.target.value }))}
            />
            <Input
              label="Standby (jam)"
              type="number"
              min={0}
              value={createForm.standby_hours}
              onChange={(e) => setCreateForm((p) => ({ ...p, standby_hours: e.target.value }))}
            />
            <Input
              label="Breakdown (jam)"
              type="number"
              min={0}
              value={createForm.breakdown_hours}
              onChange={(e) => setCreateForm((p) => ({ ...p, breakdown_hours: e.target.value }))}
            />
          </div>
          <Textarea
            label="Catatan"
            rows={2}
            value={createForm.notes}
            onChange={(e) => setCreateForm((p) => ({ ...p, notes: e.target.value }))}
          />
          <div className="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <Button variant="outline" type="button" onClick={() => setCreateOpen(false)} disabled={creating}>
              Batal
            </Button>
              <Button type="button" isLoading={creating} onClick={handleCreate}>
                Simpan & Terbitkan Tagihan
              </Button>
          </div>
        </div>
      </Modal>

      <ConfirmDialog
        isOpen={approveTarget !== null}
        onClose={() => !actingId && setApproveTarget(null)}
        onConfirm={handleApprove}
        title="Setujui Timesheet"
        message={`Timesheet #${approveTarget?.id ?? ''} akan disetujui dan terkunci final.`}
        confirmText="Setujui"
        cancelText="Batal"
        variant="primary"
        isLoading={actingId !== null}
      />

      <ConfirmDialog
        isOpen={rejectTarget !== null}
        onClose={() => !actingId && setRejectTarget(null)}
        onConfirm={handleReject}
        title="Tolak Timesheet"
        message="Timesheet akan dikembalikan untuk koreksi. Alasan tersimpan sebagai riwayat revisi."
        confirmText="Tolak & Perlu Koreksi"
        cancelText="Batal"
        variant="danger"
        isLoading={actingId !== null}
      >
        <Textarea
          label="Alasan Penolakan"
          required
          rows={3}
          value={rejectTarget?.reason ?? ''}
          onChange={(e) => {
            setRejectTarget((prev) => (prev ? { ...prev, reason: e.target.value } : prev))
            setRejectError('')
          }}
          placeholder="Contoh: jam kerja tidak sesuai meter unit…"
          error={rejectError}
          className="w-full mt-3"
        />
      </ConfirmDialog>

      <ConfirmDialog
        isOpen={reviseTarget !== null}
        onClose={() => !actingId && setReviseTarget(null)}
        onConfirm={handleRevise}
        title="Koreksi Timesheet"
        message="Perubahan disimpan sebagai snapshot revisi (append-only). Timesheet kembali menunggu validasi."
        confirmText="Simpan Koreksi"
        cancelText="Batal"
        variant="primary"
        isLoading={actingId !== null}
      >
        <div className="grid grid-cols-2 gap-3 w-full mt-3 text-left">
          <Input
            label="Jam Mulai (HM)"
            value={reviseTarget?.start_hm ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, start_hm: e.target.value } : prev))}
            error={reviseErrors.start_hm}
          />
          <Input
            label="Jam Akhir (HM)"
            value={reviseTarget?.end_hm ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, end_hm: e.target.value } : prev))}
            error={reviseErrors.end_hm}
          />
          <Input
            label="Break (menit)"
            type="number"
            min={0}
            value={reviseTarget?.break_minutes ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, break_minutes: e.target.value } : prev))}
          />
          <Input
            label="Standby (jam)"
            type="number"
            min={0}
            step="0.5"
            value={reviseTarget?.standby_hours ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, standby_hours: e.target.value } : prev))}
          />
          <Input
            label="Breakdown (jam)"
            type="number"
            min={0}
            step="0.5"
            value={reviseTarget?.breakdown_hours ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, breakdown_hours: e.target.value } : prev))}
          />
          <Textarea
            label="Alasan Koreksi"
            required
            rows={3}
            value={reviseTarget?.reason ?? ''}
            onChange={(e) => setReviseTarget((prev) => (prev ? { ...prev, reason: e.target.value } : prev))}
            error={reviseErrors.reason}
            className="col-span-2"
          />
        </div>
      </ConfirmDialog>
    </div>
  )
}
export default AdminTimesheetsPage