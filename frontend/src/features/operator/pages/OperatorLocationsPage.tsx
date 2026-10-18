import React, { useEffect, useState } from 'react'
import { MapPin } from 'lucide-react'
import { operatorService } from '../services/operatorService'
import type { OperatorAssignment, OperatorProjectLocation } from '../services/operatorService'
import { Card } from '@/components/ui/Card'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

export const OperatorLocationsPage: React.FC = () => {
  const [locations, setLocations] = useState<OperatorProjectLocation[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    operatorService
      .getAssignments()
      .then((res) => {
        const map = new Map<number, OperatorProjectLocation>()
        ;(res.data ?? []).forEach((a: OperatorAssignment) => {
          if (a.project_location) map.set(a.project_location.id, a.project_location)
        })
        setLocations([...map.values()])
      })
      .catch((err) => setApiError(err?.message || 'Gagal memuat lokasi proyek.'))
      .finally(() => setIsLoading(false))
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <MapPin className="text-primary-600" size={24} /> Lokasi Proyek
        </h2>
        <p className="text-sm text-slate-500 mt-1">Lokasi dari penugasan aktif Anda.</p>
      </div>
      {apiError && <Alert variant="danger" title="Gagal Memuat">{apiError}</Alert>}
      {isLoading ? (
        <Card className="p-5"><Skeleton className="h-20 w-full" /></Card>
      ) : locations.length === 0 ? (
        <EmptyState icon={<MapPin className="w-12 h-12" />} title="Belum Ada Lokasi" description="Belum ada lokasi proyek pada penugasan aktif Anda." />
      ) : (
        <div className="space-y-3">
          {locations.map((loc) => (
            <Card key={loc.id} className="p-4 space-y-1">
              <p className="font-semibold text-slate-900">{loc.project_name}</p>
              <p className="text-sm text-slate-600">{loc.address}, {loc.city}</p>
              {loc.latitude !== null && loc.longitude !== null && (
                <a
                  className="text-xs text-primary-700 hover:underline"
                  target="_blank"
                  rel="noreferrer"
                  href={`https://www.google.com/maps/dir/?api=1&destination=${loc.latitude},${loc.longitude}`}
                >
                  Navigasi ({loc.latitude}, {loc.longitude})
                </a>
              )}
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}

export default OperatorLocationsPage
