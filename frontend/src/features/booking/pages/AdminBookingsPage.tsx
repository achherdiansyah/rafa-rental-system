import React, { useState, useEffect } from 'react'
import { MapPin, Truck, CheckCircle2, XCircle, UserCheck, RefreshCw } from 'lucide-react'
import { bookingService } from '@/features/booking/services/bookingService'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import type { Booking } from '@/types/booking'
import type { BookingStatus } from '@/types/booking'
import type { EquipmentUnit } from '@/types/equipment'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Textarea } from '@/components/form/Textarea'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary',
  PENDING_APPROVAL: 'warning',
  APPROVED: 'default',
  PAYMENT_PENDING: 'default',
  CONFIRMED: 'success',
  DISPATCHED: 'warning',
  ARRIVED: 'secondary',
  ONGOING: 'success',
  COMPLETED: 'success',
  REJECTED: 'danger',
  CANCELLED: 'danger',
  EXPIRED: 'outline',
}

const STATUS_OPTIONS = ['', 'PENDING_APPROVAL', 'APPROVED', 'REJECTED', 'CONFIRMED', 'CANCELLED'] as const

interface UnitSelections {
  [detailId: number]: number[]
}

export const AdminBookingsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [bookings, setBookings] = useState<Booking[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [statusFilter, setStatusFilter] = useState<string>('')

  // Action state
  const [actingId, setActingId] = useState<number | null>(null)

  // Reject modal
  const [rejectTarget, setRejectTarget] = useState<Booking | null>(null)
  const [rejectReason, setRejectReason] = useState('')
  const [rejectError, setRejectError] = useState('')

  // Assign modal
  const [assignTarget, setAssignTarget] = useState<Booking | null>(null)
  const [availableUnits, setAvailableUnits] = useState<Record<number, EquipmentUnit[]>>({})
  const [selections, setSelections] = useState<UnitSelections>({})
  const [isAssigning, setIsAssigning] = useState(false)

  // Replace modal
  const [replaceTarget, setReplaceTarget] = useState<{ booking: Booking; assignmentId: number; modelId: number } | null>(null)
  const [replaceOptions, setReplaceOptions] = useState<EquipmentUnit[]>([])
  const [replaceSelection, setReplaceSelection] = useState('')
  const [replaceReason, setReplaceReason] = useState('')
  const [replaceError, setReplaceError] = useState('')
  const [isReplacing, setIsReplacing] = useState(false)

  const loadBookings = async (status: BookingStatus | string = statusFilter) => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await bookingService.getBookings({
        status: (status || undefined) as BookingStatus | undefined,
        per_page: 30,
      })
      if (res.success && res.data) {
        setBookings(res.data)
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat antrean booking.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadBookings()
  }, [statusFilter])

  const handleApprove = async (booking: Booking) => {
    setActingId(booking.id)
    try {
      const res = await bookingService.approveBooking(booking.id)
      if (res.success) {
        showSuccessToast(`Booking ${booking.booking_code} disetujui.`)
        loadBookings()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menyetujui booking.')
    } finally {
      setActingId(null)
    }
  }

  const handleReject = async () => {
    if (!rejectTarget) return
    if (rejectReason.trim().length < 10) {
      setRejectError('Alasan penolakan minimal 10 karakter.')
      return
    }
    setActingId(rejectTarget.id)
    try {
      const res = await bookingService.rejectBooking(rejectTarget.id, rejectReason)
      if (res.success) {
        showSuccessToast(`Booking ${rejectTarget.booking_code} ditolak.`)
        setRejectTarget(null)
        setRejectReason('')
        setRejectError('')
        loadBookings()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menolak booking.')
    } finally {
      setActingId(null)
    }
  }

  const openAssignModal = async (booking: Booking) => {
    setAssignTarget(booking)
    setSelections({})

    // Fetch available units per detail model
    const map: Record<number, EquipmentUnit[]> = {}
    for (const detail of booking.details ?? []) {
      try {
        const res = await equipmentService.getUnits({
          equipment_model_id: detail.equipment_model_id,
          status: 'AVAILABLE',
          per_page: 100,
        })
        if (res.success && res.data) {
          map[detail.id] = res.data
        }
      } catch {
        map[detail.id] = []
      }
    }
    setAvailableUnits(map)
  }

  const toggleUnit = (detailId: number, unitId: number) => {
    setSelections((prev) => {
      const current = prev[detailId] ?? []
      const next = current.includes(unitId)
        ? current.filter((id) => id !== unitId)
        : [...current, unitId]
      return { ...prev, [detailId]: next }
    })
  }

  const handleAssign = async () => {
    if (!assignTarget) return

    const assignments: { booking_detail_id: number; equipment_unit_id: number }[] = []
    for (const detail of assignTarget.details ?? []) {
      const selected = selections[detail.id] ?? []
      if (selected.length !== detail.quantity) {
        showErrorToast(
          `Detail ${detail.model?.brand ?? ''} ${detail.model?.model_name ?? ''} membutuhkan ${detail.quantity} unit, dipilih ${selected.length}.`
        )
        return
      }
      selected.forEach((unitId) => assignments.push({ booking_detail_id: detail.id, equipment_unit_id: unitId }))
    }

    setIsAssigning(true)
    try {
      const res = await bookingService.assignUnits(assignTarget.id, assignments)
      if (res.success) {
        showSuccessToast('Unit fisik berhasil ditugaskan.')
        setAssignTarget(null)
        loadBookings()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menugaskan unit.')
    } finally {
      setIsAssigning(false)
    }
  }

  const openReplaceModal = async (booking: Booking, assignmentId: number, modelId: number) => {
    setReplaceTarget({ booking, assignmentId, modelId })
    setReplaceSelection('')
    setReplaceReason('')
    setReplaceError('')
    try {
      const res = await equipmentService.getUnits({ equipment_model_id: modelId, status: 'AVAILABLE', per_page: 100 })
      if (res.success && res.data) {
        setReplaceOptions(res.data)
      } else {
        setReplaceOptions([])
      }
    } catch {
      setReplaceOptions([])
    }
  }

  const handleReplace = async () => {
    if (!replaceTarget) return
    if (!replaceSelection) {
      setReplaceError('Pilih unit pengganti terlebih dahulu.')
      return
    }
    if (replaceReason.trim().length < 5) {
      setReplaceError('Alasan penggantian minimal 5 karakter.')
      return
    }
    setIsReplacing(true)
    try {
      const res = await bookingService.replaceUnit(
        replaceTarget.booking.id,
        replaceTarget.assignmentId,
        Number(replaceSelection),
        replaceReason
      )
      if (res.success) {
        showSuccessToast('Unit fisik berhasil diganti.')
        setReplaceTarget(null)
        loadBookings()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengganti unit.')
    } finally {
      setIsReplacing(false)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Antrean Booking</h2>
          <p className="text-sm text-slate-500">Persetujuan dan penugasan unit fisik booking pelanggan.</p>
        </div>

        {/* Status filter */}
        <select
          value={statusFilter}
          onChange={(e) => setStatusFilter(e.target.value)}
          className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-600"
        >
          <option value="">Semua Status</option>
          {STATUS_OPTIONS.filter(Boolean).map((s) => (
            <option key={s} value={s}>
              {s}
            </option>
          ))}
        </select>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Antrean">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-5 space-y-4">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-16 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : bookings.length > 0 ? (
        <div className="space-y-4">
          {bookings.map((booking) => (
            <Card key={booking.id} className="p-5">
              <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div className="space-y-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-sm font-bold text-slate-900">{booking.booking_code}</span>
                    <Badge variant={STATUS_VARIANT[booking.status] ?? 'secondary'} size="sm">
                      {booking.status}
                    </Badge>
                    <Badge variant="secondary" size="sm">{booking.user_name || `User #${booking.user_id}`}</Badge>
                  </div>

                  {booking.project_location && (
                    <div className="flex items-start gap-2 text-sm text-slate-600">
                      <MapPin size={14} className="text-primary-600 shrink-0 mt-0.5" />
                      <span>
                        <strong className="text-slate-900">{booking.project_location.project_name}</strong>
                        <span className="block text-xs text-slate-500">
                          {booking.project_location.address}, {booking.project_location.city}
                        </span>
                      </span>
                    </div>
                  )}
                  <div className="text-xs text-slate-500">
                    {(booking.details?.length ?? 0)} item armada • {new Date(booking.created_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                  </div>

                  {/* Line summary */}
                  {booking.details && booking.details.length > 0 && (
                    <div className="flex flex-col gap-2 mt-2">
                      {booking.details.map((d) => {
                        const current = (d.unit_assignments ?? []).filter((a) => a.is_current)
                        return (
                          <div key={d.id} className="text-xs bg-slate-50 border border-slate-100 rounded-lg p-2.5 space-y-1.5">
                            <div className="flex flex-wrap items-center gap-2 justify-between">
                              <span className="font-semibold text-slate-800 flex items-center gap-1.5">
                                <Truck size={12} className="text-slate-400" />
                                {d.model?.brand} {d.model?.model_name} × {d.quantity} unit
                              </span>
                              <Badge variant={d.is_all_in ? 'default' : 'secondary'} size="sm">
                                {d.is_all_in ? 'All-in' : 'Non All-in'}
                              </Badge>
                            </div>
                            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-slate-500">
                              <span>Periode: {new Date(d.start_date).toLocaleDateString('id-ID')} - {new Date(d.end_date).toLocaleDateString('id-ID')}</span>
                              {Number(d.mob_cost_snapshot || 0) === 0 && Number(d.demob_cost_snapshot || 0) === 0 && Number(d.subtotal || 0) > 0 ? (
                                <span className="font-medium text-slate-700">Biaya MOB/DEMOB/Pengiriman: Rp {Number(d.subtotal).toLocaleString('id-ID')}</span>
                              ) : (
                                <>
                                  <span>MOB/Unit: Rp {Number(d.mob_cost_snapshot || 0).toLocaleString('id-ID')}</span>
                                  <span>DEMOB/Unit: Rp {Number(d.demob_cost_snapshot || 0).toLocaleString('id-ID')}</span>
                                  <span className="font-medium text-slate-700">Subtotal MOB/DEMOB: Rp {((Number(d.mob_cost_snapshot || 0) + Number(d.demob_cost_snapshot || 0)) * d.quantity).toLocaleString('id-ID')}</span>
                                </>
                              )}
                            </div>
                            {current.length > 0 && (
                              <div className="mt-1.5 pt-1.5 border-t border-slate-200/60 flex flex-wrap items-center gap-1.5">
                                <span className="text-emerald-700 font-medium">Unit Assigned:</span>
                                {current.map((a) => (
                                  <span key={a.id} className="inline-flex items-center gap-1.5 bg-white border border-emerald-200 px-1.5 py-0.5 rounded text-emerald-800">
                                    <span className="font-mono">{a.unit?.serial_number}</span>
                                    {booking.status === 'APPROVED' && (
                                      <button type="button" onClick={() => openReplaceModal(booking, a.id, d.equipment_model_id)} className="text-primary-600 hover:text-primary-800 font-medium cursor-pointer underline-offset-2 hover:underline ml-1">Ganti</button>
                                    )}
                                  </span>
                                ))}
                              </div>
                            )}
                          </div>
                        )
                      })}
                    </div>
                  )}
                </div>

                <div className="shrink-0 flex flex-col items-end gap-2">
                  <div className="text-right">
                    <p className="text-[10px] uppercase tracking-wider text-slate-400 font-bold mb-0.5">Total Estimasi Booking (MOB/DEMOB)</p>
                    <p className="font-mono text-lg font-bold text-slate-900">
                      {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(booking.total_amount)}
                    </p>
                  </div>

                  {booking.status === 'PENDING_APPROVAL' && (
                    <div className="flex gap-2">
                      <Button variant="primary" size="sm" className="gap-1.5" isLoading={actingId === booking.id} onClick={() => handleApprove(booking)}>
                        <CheckCircle2 size={15} /> Setujui
                      </Button>
                      <Button
                        variant="outline"
                        size="sm"
                        className="text-rose-600 hover:bg-rose-50 gap-1.5"
                        disabled={actingId === booking.id}
                        onClick={() => {
                          setRejectTarget(booking)
                          setRejectReason('')
                          setRejectError('')
                        }}
                      >
                        <XCircle size={15} /> Tolak
                      </Button>
                    </div>
                  )}

                  {booking.status === 'APPROVED' && (
                    <Button variant="outline" size="sm" className="gap-1.5" onClick={() => openAssignModal(booking)}>
                      <UserCheck size={15} /> Tugaskan Unit
                    </Button>
                  )}

                  {booking.status === 'REJECTED' && booking.rejection_reason && (
                    <span className="text-xs text-rose-600 max-w-xs text-right">{booking.rejection_reason}</span>
                  )}
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState title="Tidak Ada Booking" description="Tidak ada booking yang cocok dengan filter status saat ini." />
      )}

      {/* Reject modal */}
      <Modal
        isOpen={rejectTarget !== null}
        onClose={() => setRejectTarget(null)}
        title={`Tolak Booking ${rejectTarget?.booking_code ?? ''}`}
        size="sm"
      >
        <div className="space-y-4">
          <Textarea
            label="Alasan Penolakan * (min. 10 karakter)"
            placeholder="Contoh: Ketersediaan armada di periode tersebut tidak memadai."
            value={rejectReason}
            onChange={(e) => setRejectReason(e.target.value)}
            error={rejectError}
            rows={3}
          />
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setRejectTarget(null)} disabled={actingId !== null}>
              Batal
            </Button>
            <Button
              variant="danger"
              isLoading={actingId === rejectTarget?.id}
              onClick={handleReject}
              className="gap-1.5"
            >
              <XCircle size={16} /> Tolak Booking
            </Button>
          </div>
        </div>
      </Modal>

      {/* Assign unit modal */}
      <Modal
        isOpen={assignTarget !== null}
        onClose={() => setAssignTarget(null)}
        title={`Tugaskan Unit — ${assignTarget?.booking_code ?? ''}`}
        size="lg"
      >
        <div className="space-y-5">
          {assignTarget?.details?.map((detail) => (
            <div key={detail.id} className="border border-slate-100 rounded-xl p-4 bg-slate-50/40">
              <div className="flex items-center justify-between mb-3">
                <div className="flex items-center gap-2">
                  <Truck size={16} className="text-slate-400" />
                  <span className="font-medium text-slate-900">
                    {detail.model?.brand} {detail.model?.model_name}
                  </span>
                  <Badge variant="secondary" size="sm">
                    Butuh {detail.quantity} unit
                  </Badge>
                </div>
                <Badge variant={detail.is_all_in ? 'default' : 'secondary'} size="sm">
                  {detail.is_all_in ? 'All-in' : 'Bare'}
                </Badge>
              </div>

              {selections[detail.id]?.length === detail.quantity && (
                <div className="text-xs text-emerald-600 mb-2 font-medium">
                  âœ” Kuota terpenuhi ({selections[detail.id].length}/{detail.quantity})
                </div>
              )}

              <div className="space-y-1.5 max-h-40 overflow-y-auto pr-1">
                {(availableUnits[detail.id] ?? []).length === 0 ? (
                  <p className="text-xs text-slate-500">Tidak ada unit AVAILABLE untuk model ini.</p>
                ) : (
                  availableUnits[detail.id].map((unit) => {
                    const selected = (selections[detail.id] ?? []).includes(unit.id)
                    const exhausted = (selections[detail.id] ?? []).length >= detail.quantity
                    return (
                      <label
                        key={unit.id}
                        className={`flex items-center gap-2 text-sm p-2 rounded-lg border cursor-pointer transition-colors ${
                          selected ? 'border-primary-500 bg-primary-50' : 'border-slate-200 bg-white hover:border-slate-300'
                        } ${!selected && exhausted ? 'opacity-40 pointer-events-none' : ''}`}
                      >
                        <input
                          type="checkbox"
                          checked={selected}
                          onChange={() => toggleUnit(detail.id, unit.id)}
                          className="accent-primary-600"
                        />
                        <span className="font-mono text-xs">{unit.serial_number}</span>
                        <span className="text-xs text-slate-500 ml-auto">{unit.plate_number ?? 'â€”'}</span>
                      </label>
                    )
                  })
                )}
              </div>
            </div>
          ))}

          <div className="flex justify-end gap-3 pt-3 border-t border-slate-100">
            <Button variant="outline" onClick={() => setAssignTarget(null)} disabled={isAssigning}>
              Batal
            </Button>
            <Button isLoading={isAssigning} onClick={handleAssign} className="gap-1.5">
              <UserCheck size={16} /> Tugaskan Unit
            </Button>
          </div>
        </div>
      </Modal>

      {/* Replace unit modal */}
      <Modal
        isOpen={replaceTarget !== null}
        onClose={() => !isReplacing && setReplaceTarget(null)}
        title={`Ganti Unit â€” ${replaceTarget?.booking.booking_code ?? ''}`}
        size="md"
      >
        <div className="space-y-4">
          <Select
            label="Unit Pengganti *"
            value={replaceSelection}
            onChange={(e) => setReplaceSelection(e.target.value)}
            error={replaceError}
            disabled={isReplacing}
          >
            <option value="">-- Pilih Unit AVAILABLE --</option>
            {replaceOptions.map((u) => (
              <option key={u.id} value={u.id}>
                {u.serial_number} â€” {u.plate_number ?? 'tanpa plat'}
              </option>
            ))}
          </Select>
          {replaceOptions.length === 0 && (
            <p className="text-xs text-slate-500">Tidak ada unit AVAILABLE untuk model ini saat ini.</p>
          )}
          <Textarea
            label="Alasan Penggantian * (min. 5 karakter)"
            placeholder="Contoh: Mesin rusak pra-kirim, diganti unit cadangan."
            value={replaceReason}
            onChange={(e) => setReplaceReason(e.target.value)}
            error={replaceError}
            rows={3}
            disabled={isReplacing}
          />
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setReplaceTarget(null)} disabled={isReplacing}>
              Batal
            </Button>
            <Button isLoading={isReplacing} onClick={handleReplace} className="gap-1.5">
              <RefreshCw size={16} /> Ganti Unit
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
export default AdminBookingsPage
