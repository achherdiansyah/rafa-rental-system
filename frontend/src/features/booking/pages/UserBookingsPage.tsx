import React, { useState, useEffect } from 'react'
import { CalendarCheck, MapPin, Truck, ShieldCheck, Clock, Check } from 'lucide-react'
import { bookingService } from '../services/bookingService'
import type { Booking } from '@/types/booking'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Pagination } from '@/components/data-display/Pagination'
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
                <div className="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
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
                  ) : (
                    <span className="flex items-center gap-1.5 text-xs text-slate-400">
                      <Check size={14} className="text-emerald-500" />
                      Booking telah disubmit ke tim operasional.
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
    </div>
  )
}
export default UserBookingsPage