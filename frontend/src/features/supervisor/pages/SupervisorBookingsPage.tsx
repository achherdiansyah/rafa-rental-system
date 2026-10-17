import React, { useEffect, useState } from 'react'
import { CheckSquare, MapPin, Truck, CheckCircle2, Undo2, ShieldCheck } from 'lucide-react'
import { supervisorService } from '../services/supervisorService'
import type { Booking } from '@/types/booking'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Modal } from '@/components/ui/Modal'
import { Textarea } from '@/components/form/Textarea'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

export const SupervisorBookingsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()
  const [bookings, setBookings] = useState<Booking[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)
  const [actingId, setActingId] = useState<number | null>(null)

  const [validateTarget, setValidateTarget] = useState<Booking | null>(null)
  const [validateNotes, setValidateNotes] = useState('')

  const [returnTarget, setReturnTarget] = useState<Booking | null>(null)
  const [returnNotes, setReturnNotes] = useState('')
  const [returnError, setReturnError] = useState('')

  const load = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await supervisorService.getQueue({ per_page: 50 })
      if (res.success && res.data) setBookings(res.data)
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat antrean validasi.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    load()
  }, [])

  const handleValidate = async () => {
    if (!validateTarget) return
    setActingId(validateTarget.id)
    try {
      const res = await supervisorService.validate(validateTarget.id, validateNotes || undefined)
      if (res.success) {
        showSuccessToast(`Booking ${validateTarget.booking_code} lolos validasi.`)
        setValidateTarget(null)
        setValidateNotes('')
        load()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memvalidasi booking.')
    } finally {
      setActingId(null)
    }
  }

  const handleReturn = async () => {
    if (!returnTarget) return
    if (returnNotes.trim().length < 5) {
      setReturnError('Catatan pengembalian minimal 5 karakter.')
      return
    }
    setActingId(returnTarget.id)
    try {
      const res = await supervisorService.returnForRevision(returnTarget.id, returnNotes)
      if (res.success) {
        showSuccessToast(`Booking ${returnTarget.booking_code} dikembalikan untuk perbaikan.`)
        setReturnTarget(null)
        setReturnNotes('')
        setReturnError('')
        load()
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengembalikan booking.')
    } finally {
      setActingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <ShieldCheck className="text-primary-600" size={24} /> Antrean Validasi Booking
        </h2>
        <p className="text-sm text-slate-500 mt-1">
          Validasi awal pengajuan pelanggan sebelum diteruskan ke persetujuan Admin.
        </p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Antrean">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
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
                    {booking.supervisor_decision === 'REVISION' ? (
                      <Badge variant="warning" size="sm">Perlu Perbaikan</Badge>
                    ) : (
                      <Badge variant="secondary" size="sm">Menunggu Validasi</Badge>
                    )}
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

                  <div className="space-y-1.5">
                    {booking.details?.map((d) => (
                      <div key={d.id} className="text-xs bg-slate-50 border border-slate-100 rounded-lg p-2.5 flex flex-wrap items-center gap-x-4 gap-y-1">
                        <span className="font-semibold text-slate-800 flex items-center gap-1.5">
                          <Truck size={12} className="text-slate-400" />
                          {d.model?.brand} {d.model?.model_name} x {d.quantity}
                        </span>
                        <span className="text-slate-500">{d.start_date} s/d {d.end_date}</span>
                      </div>
                    ))}
                  </div>

                  {booking.supervisor_decision === 'REVISION' && booking.supervisor_notes && (
                    <p className="text-xs text-amber-700 bg-amber-50 border border-amber-100 rounded-lg p-2">
                      Catatan sebelumnya: {booking.supervisor_notes}
                    </p>
                  )}
                </div>

                <div className="shrink-0 flex gap-2">
                  <Button
                    variant="primary"
                    size="sm"
                    className="gap-1.5"
                    isLoading={actingId === booking.id}
                    onClick={() => {
                      setValidateTarget(booking)
                      setValidateNotes('')
                    }}
                  >
                    <CheckCircle2 size={15} /> Validasi
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-amber-700 hover:bg-amber-50 gap-1.5"
                    disabled={actingId === booking.id}
                    onClick={() => {
                      setReturnTarget(booking)
                      setReturnNotes('')
                      setReturnError('')
                    }}
                  >
                    <Undo2 size={15} /> Kembalikan
                  </Button>
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState
          icon={<CheckSquare className="w-12 h-12" />}
          title="Tidak Ada Antrean"
          description="Tidak ada booking yang menunggu validasi Supervisor saat ini."
        />
      )}

      <Modal isOpen={validateTarget !== null} onClose={() => !actingId && setValidateTarget(null)} title={`Validasi ${validateTarget?.booking_code ?? ''}`} size="sm">
        <div className="space-y-4">
          <Textarea
            label="Catatan Validasi (opsional)"
            placeholder="Contoh: Data pelanggan dan lokasi proyek sesuai."
            value={validateNotes}
            onChange={(e) => setValidateNotes(e.target.value)}
            rows={3}
            disabled={actingId !== null}
          />
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setValidateTarget(null)} disabled={actingId !== null}>Batal</Button>
            <Button isLoading={actingId === validateTarget?.id} onClick={handleValidate} className="gap-1.5">
              <CheckCircle2 size={16} /> Teruskan ke Admin
            </Button>
          </div>
        </div>
      </Modal>

      <Modal isOpen={returnTarget !== null} onClose={() => !actingId && setReturnTarget(null)} title={`Kembalikan ${returnTarget?.booking_code ?? ''}`} size="sm">
        <div className="space-y-4">
          <Textarea
            label="Catatan Perbaikan * (min. 5 karakter)"
            placeholder="Contoh: Tanggal pelaksanaan bertabrakan dengan jadwal armada, mohon diubah."
            value={returnNotes}
            onChange={(e) => setReturnNotes(e.target.value)}
            error={returnError}
            rows={3}
            disabled={actingId !== null}
          />
          <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
            <Button variant="outline" onClick={() => setReturnTarget(null)} disabled={actingId !== null}>Batal</Button>
            <Button isLoading={actingId === returnTarget?.id} onClick={handleReturn} className="gap-1.5 bg-amber-600 hover:bg-amber-700">
              <Undo2 size={16} /> Kembalikan untuk Perbaikan
            </Button>
          </div>
        </div>
      </Modal>
    </div>
  )
}

export default SupervisorBookingsPage
