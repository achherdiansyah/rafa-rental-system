import React, { useState, useEffect, useRef } from 'react'
import { Clock, FileSignature, History, CheckCircle2, XCircle, Eye } from 'lucide-react'
import { timesheetService } from '../services/timesheetService'
import type { Timesheet, TimesheetRevision } from '@/types/timesheet'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Modal } from '@/components/ui/Modal'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary',
  SUBMITTED: 'warning',
  APPROVED: 'success',
  REJECTED: 'danger',
}

const STATUS_LABEL: Record<string, string> = {
  DRAFT: 'Draft',
  SUBMITTED: 'Menunggu Konfirmasi Anda',
  APPROVED: 'Tervalidasi',
  REJECTED: 'Ditolak',
}

const fmtHours = (h: number | null | undefined) => (h === null || h === undefined || Number.isNaN(h) ? '-' : `${h} jam`)

export const UserTimesheetsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [timesheets, setTimesheets] = useState<Timesheet[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [detail, setDetail] = useState<Timesheet | null>(null)
  const [signingId, setSigningId] = useState<number | null>(null)
  const [revisions, setRevisions] = useState<Record<number, TimesheetRevision[]>>({})
  const [expandedRevision, setExpandedRevision] = useState<number | null>(null)
  const signInputRefs = useRef<Record<number, HTMLInputElement | null>>({})

  const loadAll = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const tsRes = await timesheetService.getTimesheets({ per_page: 50 })
      if (tsRes.success && tsRes.data) setTimesheets(tsRes.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat data timesheet.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadAll()
  }, [])

  // Only an owner-of-rental USER may confirm/sign; never create/edit/validate.
  const canSign = (t: Timesheet) => t.status === 'SUBMITTED'

  const handleSign = async (timesheet: Timesheet, file: File) => {
    setSigningId(timesheet.id)
    try {
      const res = await timesheetService.sign(timesheet.id, file)
      if (res.success && res.data) {
        showSuccessToast('Konfirmasi dan tanda tangan berhasil dilampirkan.')
        await loadAll()
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

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Timesheet Harian</h2>
        <p className="text-sm text-slate-500 mt-1">
          Lihat catatan pekerjaan aktual dan konfirmasi timesheet proyek Anda.
        </p>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Data">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Skeleton key={i} className="h-32 w-full rounded-2xl" />
          ))}
        </div>
      ) : timesheets.length === 0 ? (
        <EmptyState
          title="Belum Ada Timesheet"
          description="Belum ada catatan pekerjaan harian untuk rental Anda. Timesheet diinput oleh Admin berdasarkan laporan operator, lalu Anda konfirmasi."
        />
      ) : (
        <div className="space-y-4">
          {timesheets.map((t) => (
            <div key={t.id} className="rounded-2xl border border-slate-200 bg-white p-5">
              <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div className="flex items-center gap-2">
                    <Badge variant={STATUS_VARIANT[t.status] ?? 'secondary'} size="sm">
                      {STATUS_LABEL[t.status] ?? t.status}
                    </Badge>
                    <span className="text-xs text-slate-500">{t.report_date}</span>
                  </div>
                  <h4 className="font-semibold text-slate-900 mt-2">{t.rental?.unit_serial ?? 'Unit'}</h4>
                  <p className="text-xs text-slate-500">
                    {t.rental?.booking_code ?? 'Rental'} · {t.rental?.project_name ?? '—'} · Operator: {t.operator_name ?? '—'}
                  </p>
                </div>
                <div className="text-right">
                  <span className="font-mono font-bold text-slate-900">{fmtHours(t.total_work_hours)}</span>
                  <span className="block text-xs text-slate-400">Actual Working Hours</span>
                </div>
              </div>

              <div className="mt-4 flex flex-wrap gap-2">
                <Button variant="outline" size="sm" onClick={() => setDetail(t)} leftIcon={<Eye size={14} />}>
                  Detail
                </Button>
                {canSign(t) && (
                  <>
                    <Button
                      variant="primary"
                      size="sm"
                      isLoading={signingId === t.id}
                      disabled={signingId !== null}
                      onClick={() => signInputRefs.current[t.id]?.click()}
                      leftIcon={<FileSignature size={14} />}
                    >
                      Konfirmasi & Tanda Tangan
                    </Button>
                    <input
                      ref={(el) => {
                        signInputRefs.current[t.id] = el
                      }}
                      type="file"
                      accept="image/jpeg,image/png,image/webp"
                      className="hidden"
                      onChange={(e) => {
                        const f = e.target.files?.[0]
                        if (f) handleSign(t, f)
                        e.target.value = ''
                      }}
                    />
                  </>
                )}
                <Button variant="ghost" size="sm" onClick={() => toggleRevisions(t.id)} leftIcon={<History size={14} />}>
                  Riwayat Koreksi
                </Button>
              </div>

              {expandedRevision === t.id && (
                <div className="mt-3 space-y-2 bg-slate-50 rounded-xl p-3 border border-slate-100">
                  {!revisions[t.id] || revisions[t.id].length === 0 ? (
                    <p className="text-xs text-slate-400">Belum ada revisi.</p>
                  ) : (
                    revisions[t.id].map((r: TimesheetRevision) => (
                      <div key={r.version} className="text-xs text-slate-600 space-y-0.5">
                        <div className="font-medium text-slate-800">
                          v{r.version} · {r.revision_reason}
                        </div>
                        <div className="pl-3 text-slate-500">
                          HM sebelumnya {r.old_start_hm}→{r.old_end_hm}
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
          ))}

          {timesheets.some((t) => t.status === 'REJECTED') && (
            <div className="text-xs text-slate-500 flex items-center gap-1.5">
              <XCircle size={13} className="text-rose-500" /> Timesheet ditolak menunggu koreksi Admin.
            </div>
          )}
        </div>
      )}

      {/* Detail Modal */}
      <Modal isOpen={!!detail} onClose={() => setDetail(null)} title="Detail Timesheet" size="md">
        {detail ? (
          <div className="space-y-3 text-sm">
            <div className="flex items-center gap-2">
              <Badge variant={STATUS_VARIANT[detail.status] ?? 'secondary'} size="sm">
                {STATUS_LABEL[detail.status] ?? detail.status}
              </Badge>
              <span className="text-slate-500">{detail.report_date}</span>
            </div>
            <div className="grid grid-cols-2 gap-3 bg-slate-50 rounded-xl p-4 border border-slate-100">
              <div>
                <p className="text-xs text-slate-400">Unit Rental</p>
                <p className="font-medium text-slate-800">{detail.rental?.unit_serial ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Proyek</p>
                <p className="font-medium text-slate-800">{detail.rental?.project_name ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Jam Mulai</p>
                <p className="font-mono font-medium text-slate-800">{detail.start_hm}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Jam Akhir</p>
                <p className="font-mono font-medium text-slate-800">{detail.end_hm}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Break (menit)</p>
                <p className="font-medium text-slate-800">{detail.break_minutes ?? 0}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Standby / Breakdown</p>
                <p className="font-medium text-slate-800">
                  {detail.standby_hours ?? 0} / {detail.breakdown_hours ?? 0} jam
                </p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Operator</p>
                <p className="font-medium text-slate-800">{detail.operator_name ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs text-slate-400">Actual Working Hours</p>
                <p className="font-mono font-bold text-slate-900">{fmtHours(detail.total_work_hours)}</p>
              </div>
            </div>
            {detail.notes && (
              <div>
                <p className="text-xs text-slate-400">Catatan</p>
                <p className="text-slate-700">{detail.notes}</p>
              </div>
            )}
            {detail.signature_reference ? (
              <div className="flex items-center gap-2 text-emerald-700 text-xs font-medium">
                <CheckCircle2 size={14} /> Sudah dikonfirmasi & ditandatangani
              </div>
            ) : (
              <div className="flex items-center gap-2 text-slate-400 text-xs">
                <Clock size={14} /> Belum dikonfirmasi PIC
              </div>
            )}
          </div>
        ) : (
          <div className="text-slate-400">Memuat detail…</div>
        )}
      </Modal>
    </div>
  )
}

export default UserTimesheetsPage