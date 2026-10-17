import React, { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowLeft, MapPin, Truck, Clock } from 'lucide-react'
import { operatorService } from '../services/operatorService'
import type { OperatorAssignment } from '../services/operatorService'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Button } from '@/components/ui/Button'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'

export const OperatorAssignmentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()
  const [assignment, setAssignment] = useState<OperatorAssignment | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    operatorService
      .getAssignment(Number(id))
      .then((res) => {
        if (mounted && res.success && res.data) setAssignment(res.data)
      })
      .catch((err) => {
        if (mounted) setApiError(err?.message || 'Gagal memuat detail penugasan.')
      })
      .finally(() => {
        if (mounted) setIsLoading(false)
      })
    return () => {
      mounted = false
    }
  }, [id])

  return (
    <div className="space-y-6 max-w-3xl">
      <Link to="/operator/assignments" className="inline-flex items-center gap-1.5 text-sm text-slate-500 hover:text-slate-800">
        <ArrowLeft size={16} /> Kembali ke Penugasan Saya
      </Link>

      {apiError && <Alert variant="danger" title="Gagal Memuat Detail">{apiError}</Alert>}

      {isLoading ? (
        <Card className="p-5 space-y-3">
          <Skeleton className="h-6 w-48" />
          <Skeleton className="h-24 w-full rounded-xl" />
        </Card>
      ) : assignment ? (
        <>
          <Card className="p-5 space-y-3">
            <div className="flex items-center gap-2 flex-wrap">
              <span className="font-mono text-sm font-bold text-slate-900">{assignment.booking_code ?? `Rental #${assignment.rental_id}`}</span>
              <Badge variant="success" size="sm">{assignment.status}</Badge>
              {assignment.rental_status && <Badge variant="secondary" size="sm">{assignment.rental_status}</Badge>}
            </div>
            <p className="text-sm text-slate-700 flex items-center gap-1.5">
              <Truck size={15} className="text-slate-400" />
              {assignment.unit?.serial_number ?? '-'} {assignment.unit?.plate_number ? `(${assignment.unit.plate_number})` : ''}
            </p>
            <p className="text-xs text-slate-500 flex items-center gap-1.5">
              <Clock size={12} /> Mulai: {assignment.started_at ? new Date(assignment.started_at).toLocaleString('id-ID') : '-'}
            </p>
          </Card>

          <Card className="p-5 space-y-3">
            <h3 className="font-semibold text-slate-900 flex items-center gap-2">
              <MapPin size={16} className="text-primary-600" /> Lokasi Proyek
            </h3>
            {assignment.project_location ? (
              <div className="text-sm text-slate-700 space-y-1">
                <p className="font-medium text-slate-900">{assignment.project_location.project_name}</p>
                <p>{assignment.project_location.address}</p>
                <p>{assignment.project_location.city}</p>
                {assignment.project_location.pic_name && (
                  <p className="text-slate-500">
                    PIC: {assignment.project_location.pic_name} {assignment.project_location.pic_phone ? `- ${assignment.project_location.pic_phone}` : ''}
                  </p>
                )}
                {assignment.project_location.latitude !== null && assignment.project_location.longitude !== null && (
                  <a
                    className="inline-block text-primary-700 hover:underline text-xs mt-1"
                    href={`https://www.google.com/maps?q=${assignment.project_location.latitude},${assignment.project_location.longitude}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    Buka di Google Maps ({assignment.project_location.latitude}, {assignment.project_location.longitude})
                  </a>
                )}
              </div>
            ) : (
              <p className="text-sm text-slate-400">Lokasi proyek tidak tersedia.</p>
            )}
          </Card>

          <Button variant="outline" onClick={() => window.history.back()}>Kembali</Button>
        </>
      ) : null}
    </div>
  )
}

export default OperatorAssignmentDetailPage
