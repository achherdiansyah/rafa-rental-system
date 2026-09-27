import React, { useState, useEffect } from 'react'
import { MapPin, Truck, CheckCircle2, XCircle, UserCheck } from 'lucide-react'
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
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  DRAFT: 'secondary',
  SUBMITTED: 'warning',
  PENDING_APPROVAL: 'warning',
  APPROVED: 'default',
  PAYMENT_PENDING: 'default',
  CONFIRMED: 'success',
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
                    <Badge variant="secondary" size="sm">User #{booking.user_id}</Badge>
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
                    {(booking.details?.length ?? 0)} item armada •{' '}
                    {new Date(booking.created_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                  </div>

                  {/* Line summary */}
                  {booking.details && booking.details.length > 0 && (
                    <div className="flex flex-wrap gap-2 mt-1">
                      {booking.details.map((d) => (
                        <span key={d.id} className="text-xs bg-slate-50 border border-slate-100 rounded-lg px-2 py-1">
                          <Truck size={11} className="inline mr-1 text-slate-400" />
                          {d.model?.brand} {d.model?.model_name} × {d.quantity}
                        </span>
                      ))}
                    </div>
                  )}
                </div>

                <div className="shrink-0 flex flex-col items-end gap-2">
                  <span className="font-mono text-base font-bold text-slate-900">
                    {new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(booking.total_amount)}
                  </span>

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
        onClose={() => !isAssigning && setAssignTarget(null)}
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
                  ✔ Kuota terpenuhi ({selections[detail.id].length}/{detail.quantity})
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
                        <span className="text-xs text-slate-500 ml-auto">{unit.plate_number ?? '—'}</span>
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
    </div>
  )
}
export default AdminBookingsPage