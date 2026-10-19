import React, { useState, useEffect } from 'react'
import { CalendarCheck, MapPin, Truck, ShieldCheck, Clock, Check, XCircle, CalendarClock } from 'lucide-react'
import { bookingService } from '../services/bookingService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import type { Booking } from '@/types/booking'
import type { ProjectLocation } from '@/types/projectLocation'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Input } from '@/components/form/Input'
import { Textarea } from '@/components/form/Textarea'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Pagination } from '@/components/data-display/Pagination'
import { useToast } from '@/hooks/useToast'
import {
  minRentalStartDate,
  isRentalStartValid,
  isRentalEndValid,
  RENTAL_START_MESSAGE,
  RENTAL_END_MESSAGE,
} from '@/utils/rentalDates'

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

const formatRupiah = (val: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val)
}

export const UserBookingsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [bookings, setBookings] = useState<Booking[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [currentPage, setCurrentPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  const [submittingId, setSubmittingId] = useState<number | null>(null)
  const [bookingToSubmit, setBookingToSubmit] = useState<Booking | null>(null)
  const [isSubmitConfirmOpen, setIsSubmitConfirmOpen] = useState(false)

  // Cancel
  const [cancelTarget, setCancelTarget] = useState<Booking | null>(null)
  const [cancelReason, setCancelReason] = useState('')
  const [cancelError, setCancelError] = useState('')

  // Supervisor-revision correction
  const [correctTarget, setCorrectTarget] = useState<Booking | null>(null)
  const [correctLocationId, setCorrectLocationId] = useState('')
  const [correctDetails, setCorrectDetails] = useState<{ id: number; start_date: string; end_date: string }[]>([])
  const [correctLocations, setCorrectLocations] = useState<ProjectLocation[]>([])
  const [correctError, setCorrectError] = useState('')

  // Reschedule
  const [rescheduleTarget, setRescheduleTarget] = useState<Booking | null>(null)
  const [rescheduleStart, setRescheduleStart] = useState('')
  const [rescheduleEnd, setRescheduleEnd] = useState('')
  const [rescheduleReason, setRescheduleReason] = useState('')
  const [rescheduleError, setRescheduleError] = useState('')

  const loadBookings = async (page = 1) => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await bookingService.getBookings({ page, per_page: 10 })
      if (res.success && res.data) {
        setBookings(res.data)
        if (res.meta) {
          setTotalPages(res.meta.last_page)
          setCurrentPage(res.meta.current_page)
        }
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat daftar booking.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadBookings()
  }, [])

  useEffect(() => {
    if (currentPage > 1) loadBookings(currentPage)
  }, [currentPage])

  const handleSubmitBooking = async () => {
    if (!bookingToSubmit) return
    setSubmittingId(bookingToSubmit.id)
    try {
      const res = await bookingService.submitBooking(bookingToSubmit.id)
      if (res.success && res.data) {
        showSuccessToast('Booking berhasil disubmit ke tim approval.')
        loadBookings(currentPage)
      }
    } catch (err: any) {
      const msg =
        err?.status === 409
          ? err?.message
          : 'Gagal submit booking. Pastikan profil identitas sudah terverifikasi.'
      showErrorToast(msg)
    } finally {
      setSubmittingId(null)
      setIsSubmitConfirmOpen(false)
      setBookingToSubmit(null)
    }
  }

  const isEditable = (status: string) => status === 'DRAFT'

  const canCancel = (b: Booking) =>
    !b.payment_met_at &&
    ['DRAFT', 'PENDING_APPROVAL', 'APPROVED', 'PAYMENT_PENDING'].includes(b.status)

  const canReschedule = (b: Booking) =>
    ['APPROVED', 'PAYMENT_PENDING', 'CONFIRMED'].includes(b.status)

  const handleCancel = async () => {
    if (!cancelTarget) return
    if (cancelReason.trim().length < 5) {
      setCancelError('Alasan pembatalan minimal 5 karakter.')
      return
    }
    setSubmittingId(cancelTarget.id)
    try {
      const res = await bookingService.cancelBooking(cancelTarget.id, cancelReason)
      if (res.success) {
        showSuccessToast('Booking dibatalkan.')
        setCancelTarget(null)
        setCancelReason('')
        setCancelError('')
        loadBookings(currentPage)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal membatalkan booking.')
    } finally {
      setSubmittingId(null)
    }
  }

  const handleReschedule = async () => {
    if (!rescheduleTarget) return
    const errs: string[] = []
    if (!rescheduleStart) errs.push('Tanggal mulai baru wajib diisi.')
    if (!rescheduleEnd) errs.push('Tanggal selesai baru wajib diisi.')
    if (rescheduleStart && !isRentalStartValid(rescheduleStart)) {
      errs.push(RENTAL_START_MESSAGE)
    }
    if (rescheduleStart && rescheduleEnd && !isRentalEndValid(rescheduleStart, rescheduleEnd)) {
      errs.push(RENTAL_END_MESSAGE)
    }
    if (rescheduleReason.trim().length < 5) errs.push('Alasan reschedule minimal 5 karakter.')
    if (errs.length) {
      setRescheduleError(errs.join(' '))
      return
    }
    setSubmittingId(rescheduleTarget.id)
    try {
      const res = await bookingService.rescheduleBooking(rescheduleTarget.id, {
        new_start_date: rescheduleStart,
        new_end_date: rescheduleEnd,
        reason: rescheduleReason,
      })
      if (res.success) {
        showSuccessToast('Permintaan reschedule diajukan ke tim approval.')
        setRescheduleTarget(null)
        setRescheduleStart('')
        setRescheduleEnd('')
        setRescheduleReason('')
        setRescheduleError('')
        loadBookings(currentPage)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengajukan reschedule.')
    } finally {
      setSubmittingId(null)
    }
  }

  const canCorrect = (b: Booking) =>
    b.status === 'PENDING_APPROVAL' && b.supervisor_decision === 'REVISION'

  const openCorrect = async (booking: Booking) => {
    setCorrectTarget(booking)
    setCorrectLocationId(String(booking.project_location_id ?? ''))
    setCorrectDetails(
      (booking.details ?? []).map((d) => ({ id: d.id, start_date: d.start_date, end_date: d.end_date }))
    )
    setCorrectError('')
    if (correctLocations.length === 0) {
      try {
        const res = await projectLocationService.getLocations(1, 50)
        setCorrectLocations(res.data ?? [])
      } catch {
        setCorrectLocations([])
      }
    }
  }

  const handleCorrect = async () => {
    if (!correctTarget) return
    for (const d of correctDetails) {
      if (!isRentalStartValid(d.start_date)) {
        setCorrectError(RENTAL_START_MESSAGE)
        return
      }
      if (!isRentalEndValid(d.start_date, d.end_date)) {
        setCorrectError(RENTAL_END_MESSAGE)
        return
      }
    }
    setSubmittingId(correctTarget.id)
    try {
      const res = await bookingService.updateBooking(correctTarget.id, {
        project_location_id: correctLocationId ? Number(correctLocationId) : null,
        details: correctDetails,
      })
      if (res.success) {
        await bookingService.submitBooking(correctTarget.id)
        showSuccessToast('Perbaikan disimpan dan booking diajukan ulang ke validasi Supervisor.')
        setCorrectTarget(null)
        loadBookings(currentPage)
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memperbarui booking.')
    } finally {
      setSubmittingId(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <CalendarCheck className="text-primary-600" size={24} />
          Sewa Saya
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Kelola pengajuan sewa armada alat berat Anda.
        </p>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Booking">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-4">
              <Skeleton className="h-6 w-40" />
              <Skeleton className="h-20 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : bookings.length > 0 ? (
        <div className="space-y-4">
          {bookings.map((booking) => {
            const canSubmit = isEditable(booking.status)
            const location = booking.project_location
            const detailCount = booking.details?.length ?? 0

            return (
              <Card key={booking.id} className="p-5">
                <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                  <div className="space-y-2">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-mono text-sm font-bold text-slate-900">
                        {booking.booking_code}
                      </span>
                      <Badge variant={STATUS_VARIANT[booking.status] ?? 'secondary'} size="sm">
                        {booking.status}
                      </Badge>
                    </div>

                    {/* Project Location */}
                    {location && (
                      <div className="flex items-start gap-2 text-sm text-slate-600">
                        <MapPin size={15} className="text-primary-600 shrink-0 mt-0.5" />
                        <span>
                          <strong className="text-slate-900">{location.project_name}</strong>
                          <span className="block text-xs text-slate-500">
                            {location.address}, {location.city}
                          </span>
                        </span>
                      </div>
                    )}

                    {/* Detail summary */}
                    <div className="text-xs text-slate-500">
                      {detailCount} item armada • Diajukan{' '}
                      {new Date(booking.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                      })}
                    </div>
                  </div>

                  <div className="text-right shrink-0">
                    <span className="text-xs text-slate-400 block">Total Estimasi Sewa</span>
                    <span className="font-mono text-lg font-bold text-slate-900">
                      {formatRupiah(booking.total_amount)}
                    </span>
                  </div>
                </div>

                {/* Detail lines */}
                {booking.details && booking.details.length > 0 && (
                  <div className="mt-4 space-y-2">
                    {booking.details.map((detail) => (
                      <div
                        key={detail.id}
                        className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-slate-50 border border-slate-100 rounded-xl p-3 text-sm"
                      >
                        <div className="flex items-center gap-3">
                          <Truck size={16} className="text-slate-400 shrink-0" />
                          <span className="font-medium text-slate-900">
                            {detail.model?.brand} {detail.model?.model_name}
                          </span>
                          <Badge variant={detail.is_all_in ? 'default' : 'secondary'} size="sm">
                            {detail.is_all_in ? 'All-in' : 'Bare'}
                          </Badge>
                        </div>
                        <div className="flex items-center gap-4 text-xs text-slate-500">
                          <span>{detail.quantity} unit</span>
                          <span className="flex items-center gap-1">
                            <Clock size={12} />
                            {detail.start_date} → {detail.end_date}
                          </span>
                          <span className="font-semibold text-slate-900">
                            {formatRupiah(detail.subtotal)}
                          </span>
                        </div>
                      </div>
                    ))}
                  </div>
                )}

                {/* Actions */}
                <div className="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2 flex-wrap">
                  {canSubmit ? (
                    <Button
                      variant="primary"
                      size="sm"
                      isLoading={submittingId === booking.id}
                      onClick={() => {
                        setBookingToSubmit(booking)
                        setIsSubmitConfirmOpen(true)
                      }}
                      className="gap-2"
                    >
                      <ShieldCheck size={15} />
                      Ajukan ke Approval
                    </Button>
                  ) : null}

                  {canCorrect(booking) && (
                    <Button
                      variant="primary"
                      size="sm"
                      disabled={submittingId !== null}
                      onClick={() => openCorrect(booking)}
                      className="gap-1.5"
                    >
                      <ShieldCheck size={15} />
                      Perbaiki &amp; Ajukan Ulang
                    </Button>
                  )}

                  {booking.supervisor_decision === 'REVISION' && booking.supervisor_notes && (
                    <span className="text-xs text-amber-700 max-w-md">
                      Catatan Supervisor: {booking.supervisor_notes}
                    </span>
                  )}

                  {canReschedule(booking) && (
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={submittingId !== null}
                      onClick={() => {
                        setRescheduleTarget(booking)
                        setRescheduleStart('')
                        setRescheduleEnd('')
                        setRescheduleReason('')
                        setRescheduleError('')
                      }}
                      className="gap-1.5"
                    >
                      <CalendarClock size={15} />
                      Ajukan Reschedule
                    </Button>
                  )}

                  {canCancel(booking) && (
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={submittingId !== null}
                      className="text-rose-600 hover:bg-rose-50 gap-1.5"
                      onClick={() => {
                        setCancelTarget(booking)
                        setCancelReason('')
                        setCancelError('')
                      }}
                    >
                      <XCircle size={15} />
                      Batalkan
                    </Button>
                  )}

                  {!canSubmit && !canReschedule(booking) && !canCancel(booking) && (
                    <span className="flex items-center gap-1.5 text-xs text-slate-400">
                      <Check size={14} className="text-emerald-500" />
                      Booking sedang diproses operasional.
                    </span>
                  )}
                </div>
              </Card>
            )
          })}

          {totalPages > 1 && (
            <Pagination currentPage={currentPage} totalPages={totalPages} onPageChange={setCurrentPage} />
          )}
        </div>
      ) : (
        <EmptyState
          icon={<CalendarCheck className="w-12 h-12" />}
          title="Belum Ada Pengajuan Sewa"
          description="Anda belum memiliki booking. Tambahkan armada ke keranjang lalu lanjutkan ke tahap pembuatan booking."
          action={
            <Button onClick={() => loadBookings()}>
              Muat Ulang Daftar
            </Button>
          }
        />
      )}

      {/* Submit confirmation */}
      <ConfirmDialog
        isOpen={isSubmitConfirmOpen}
        onClose={() => setIsSubmitConfirmOpen(false)}
        onConfirm={handleSubmitBooking}
        title="Ajukan Booking ke Approval"
        message={`Booking ${bookingToSubmit?.booking_code} akan diajukan ke tim operasional untuk persetujuan. Ketersediaan armada akan divalidasi ulang pada tahap ini.`}
        confirmText="Ajukan Sekarang"
        cancelText="Batal"
        isLoading={submittingId !== null}
      />

      {/* Cancel modal */}
      <Modal
        isOpen={cancelTarget !== null}
        onClose={() => setCancelTarget(null)}
        title={`Batalkan Booking ${cancelTarget?.booking_code ?? ''}`}
        size="sm"
      >
        <div className="space-y-4">
          <Textarea
            label="Alasan Pembatalan * (min. 5 karakter)"
            placeholder="Contoh: Proyek ditunda oleh pemilik gedung."
            value={cancelReason}
            onChange={(e) => setCancelReason(e.target.value)}
            error={cancelError}
            rows={3}
          />
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setCancelTarget(null)} disabled={submittingId !== null}>
              Batal
            </Button>
            <Button variant="danger" isLoading={submittingId === cancelTarget?.id} onClick={handleCancel} className="gap-1.5">
              <XCircle size={16} /> Batalkan Booking
            </Button>
          </div>
        </div>
      </Modal>

      {/* Reschedule modal */}
      <Modal
        isOpen={rescheduleTarget !== null}
        onClose={() => setRescheduleTarget(null)}
        title={`Reschedule ${rescheduleTarget?.booking_code ?? ''}`}
        size="md"
      >
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Tanggal Mulai Baru *"
              type="date"
              min={minRentalStartDate()}
              value={rescheduleStart}
              onChange={(e) => {
                const value = e.target.value
                setRescheduleStart(value)
                if (rescheduleEnd && !isRentalEndValid(value, rescheduleEnd)) {
                  setRescheduleEnd('')
                }
              }}
              disabled={submittingId !== null}
            />
            <Input
              label="Tanggal Selesai Baru *"
              type="date"
              min={rescheduleStart || minRentalStartDate()}
              value={rescheduleEnd}
              onChange={(e) => setRescheduleEnd(e.target.value)}
              disabled={submittingId !== null}
            />
          </div>
          <Textarea
            label="Alasan Reschedule * (min. 5 karakter)"
            placeholder="Contoh: Penjadwalan proyek mundur satu minggu."
            value={rescheduleReason}
            onChange={(e) => setRescheduleReason(e.target.value)}
            error={rescheduleError}
            rows={3}
          />
          <p className="text-xs text-slate-400">
            Ketersediaan & buffer jadwal akan divalidasi ulang. Kembali ke PENDING_APPROVAL untuk persetujuan Admin.
          </p>
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setRescheduleTarget(null)} disabled={submittingId !== null}>
              Batal
            </Button>
            <Button isLoading={submittingId === rescheduleTarget?.id} onClick={handleReschedule} className="gap-1.5">
              <CalendarClock size={16} /> Ajukan Reschedule
            </Button>
          </div>
        </div>
      </Modal>

      {/* Supervisor-revision correction modal */}
      <Modal
        isOpen={correctTarget !== null}
        onClose={() => submittingId === null && setCorrectTarget(null)}
        title={`Perbaiki ${correctTarget?.booking_code ?? ''}`}
        size="md"
      >
        <div className="space-y-4">
          <p className="text-sm text-slate-500">
            {correctTarget?.supervisor_notes
              ? `Catatan Supervisor: ${correctTarget.supervisor_notes}`
              : 'Sesuaikan data booking lalu ajukan ulang ke validasi Supervisor.'}
          </p>

          <div>
            <label className="block text-sm font-medium text-slate-700 mb-1.5">Lokasi Proyek</label>
            <select
              value={correctLocationId}
              onChange={(e) => setCorrectLocationId(e.target.value)}
              disabled={submittingId !== null}
              className="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm cursor-pointer focus:outline-none focus:ring-2 focus:ring-primary-600"
            >
              <option value="">-- Pilih Lokasi --</option>
              {correctLocations.map((loc) => (
                <option key={loc.id} value={loc.id}>
                  {loc.project_name} - {loc.city}
                </option>
              ))}
            </select>
          </div>

          {correctDetails.map((d, idx) => (
            <div key={d.id} className="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <Input
                label={`Mulai Item #${idx + 1}`}
                type="date"
                min={minRentalStartDate()}
                value={d.start_date}
                disabled={submittingId !== null}
                onChange={(e) => {
                  const value = e.target.value
                  setCorrectDetails((prev) =>
                    prev.map((x) =>
                      x.id === d.id
                        ? { ...x, start_date: value, end_date: isRentalEndValid(value, x.end_date) ? x.end_date : '' }
                        : x
                    )
                  )
                }}
              />
              <Input
                label={`Selesai Item #${idx + 1}`}
                type="date"
                min={d.start_date || minRentalStartDate()}
                value={d.end_date}
                disabled={submittingId !== null}
                onChange={(e) =>
                  setCorrectDetails((prev) => prev.map((x) => (x.id === d.id ? { ...x, end_date: e.target.value } : x)))
                }
              />
            </div>
          ))}

          {correctError && <p className="text-xs text-rose-600">{correctError}</p>}

          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setCorrectTarget(null)} disabled={submittingId !== null}>
              Batal
            </Button>
            <Button isLoading={submittingId === correctTarget?.id} onClick={handleCorrect} className="gap-1.5">
              <ShieldCheck size={16} /> Simpan &amp; Ajukan Ulang
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}
export default UserBookingsPage