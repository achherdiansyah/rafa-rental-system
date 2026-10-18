import React, { useEffect, useState } from 'react'
import { History, MapPin } from 'lucide-react'
import { supervisorService } from '../services/supervisorService'
import type { Booking } from '@/types/booking'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

export const SupervisorHistoryPage: React.FC = () => {
  const [bookings, setBookings] = useState<Booking[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    supervisorService
      .getQueue({ queue: 'history', per_page: 50 })
      .then((res) => setBookings(res.data ?? []))
      .catch((err) => setApiError(err?.message || 'Gagal memuat riwayat validasi.'))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <History className="text-primary-600" size={24} /> Riwayat Validasi
        </h2>
        <p className="text-sm text-slate-500 mt-1">Booking yang pernah Anda validasi atau kembalikan.</p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat">{apiError}</Alert>}

      {isLoading ? (
        <Card className="p-5"><Skeleton className="h-20 w-full" /></Card>
      ) : bookings.length === 0 ? (
        <EmptyState icon={<History className="w-12 h-12" />} title="Belum Ada Riwayat" description="Belum ada booking yang Anda validasi." />
      ) : (
        <div className="space-y-3">
          {bookings.map((booking) => (
            <Card key={booking.id} className="p-4 space-y-2">
              <div className="flex items-center gap-2 flex-wrap">
                <span className="font-mono text-sm font-bold text-slate-900">{booking.booking_code}</span>
                <Badge variant={booking.supervisor_decision === 'VALIDATED' ? 'success' : 'warning'} size="sm">
                  {booking.supervisor_decision ?? 'REVISION'}
                </Badge>
                <Badge variant="secondary" size="sm">{booking.status}</Badge>
              </div>
              {booking.project_location && (
                <p className="text-xs text-slate-500 flex items-center gap-1.5">
                  <MapPin size={12} /> {booking.project_location.project_name} — {booking.project_location.city}
                </p>
              )}
              {booking.supervisor_notes && <p className="text-xs text-slate-600">Catatan: {booking.supervisor_notes}</p>}
              {booking.supervisor_validated_at && (
                <p className="text-[11px] text-slate-400">
                  Divalidasi {new Date(booking.supervisor_validated_at).toLocaleString('id-ID')}
                </p>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

export default SupervisorHistoryPage
