import React, { useState, useEffect } from 'react'
import { Truck, MapPin } from 'lucide-react'
import { rentalService } from '../services/rentalService'
import type { Rental } from '@/types/rental'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

const STATUS_VARIANT: Record<string, 'default' | 'secondary' | 'success' | 'warning' | 'danger' | 'outline'> = {
  ASSIGNED: 'secondary',
  DISPATCHED: 'warning',
  ARRIVED: 'warning',
  ONGOING: 'success',
  DEMOBILIZING: 'warning',
  RETURN_INSPECTED: 'warning',
  COMPLETED: 'success',
  CANCELLED: 'danger',
}

export const UserRentalsPage: React.FC = () => {
  const [rentals, setRentals] = useState<Rental[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const loadRentals = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await rentalService.getRentals({ per_page: 50 })
      if (res.success && res.data) {
        setRentals(res.data)
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat daftar rental.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadRentals()
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Rental Saya</h2>
        <p className="text-sm text-slate-500 mt-1">
          Pantau pelaksanaan rental armada Anda beserta status operasionalnya.
        </p>
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Rental">
          {apiError}
        </Alert>
      )}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-4">
              <Skeleton className="h-6 w-48" />
              <Skeleton className="h-14 w-full rounded-xl" />
            </Card>
          ))}
        </div>
      ) : rentals.length > 0 ? (
        <div className="space-y-4">
          {rentals.map((rental) => (
            <Card key={rental.id} className="p-5">
              <div className="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                <div className="space-y-2">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-sm font-bold text-slate-900">
                      {rental.booking?.booking_code ?? `Rental #${rental.id}`}
                    </span>
                    <Badge variant={STATUS_VARIANT[rental.status] ?? 'secondary'} size="sm">
                      {rental.status}
                    </Badge>
                  </div>

                  {rental.booking?.project_location && (
                    <div className="flex items-start gap-2 text-sm text-slate-600">
                      <MapPin size={14} className="text-primary-600 shrink-0 mt-0.5" />
                      <span>
                        <strong className="text-slate-900">{rental.booking.project_location.project_name}</strong>
                        <span className="block text-xs text-slate-500">
                          {rental.booking.project_location.city}
                        </span>
                      </span>
                    </div>
                  )}

                  <div className="text-xs text-slate-500">
                    Dibuat {new Date(rental.created_at).toLocaleDateString('id-ID', { dateStyle: 'medium' })}
                    {rental.started_at ? ` • Mulai ${new Date(rental.started_at).toLocaleString('id-ID')}` : ''}
                  </div>

                  <div className="flex flex-wrap gap-2 mt-1">
                    {rental.details.map((d) => (
                      <span key={d.id} className="text-xs bg-slate-50 border border-slate-100 rounded-lg px-2 py-1">
                        <Truck size={11} className="inline mr-1 text-slate-400" />
                        <span className="font-mono">{d.unit?.serial_number ?? '-'}</span>
                        <span className="text-slate-400 ml-1">{d.unit?.plate_number ?? ''}</span>
                      </span>
                    ))}
                  </div>
                </div>
              </div>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState
          icon={<Truck className="w-12 h-12" />}
          title="Belum Ada Rental"
          description="Rental dibuat oleh RAFA dari booking Anda yang telah disetujui dan unit ditugaskan."
        />
      )}
    </div>
  )
}
export default UserRentalsPage