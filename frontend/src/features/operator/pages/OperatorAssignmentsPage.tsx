import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ClipboardList, Truck, MapPin, ChevronRight } from 'lucide-react'
import { operatorService } from '../services/operatorService'
import type { OperatorAssignment } from '../services/operatorService'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'

export const OperatorAssignmentsPage: React.FC = () => {
  const [assignments, setAssignments] = useState<OperatorAssignment[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    operatorService
      .getAssignments()
      .then((res) => {
        if (mounted && res.success && res.data) setAssignments(res.data)
      })
      .catch((err) => {
        if (mounted) setApiError(err?.message || 'Gagal memuat penugasan.')
      })
      .finally(() => {
        if (mounted) setIsLoading(false)
      })
    return () => {
      mounted = false
    }
  }, [])

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
          <ClipboardList className="text-primary-600" size={24} /> Penugasan Saya
        </h2>
        <p className="text-sm text-slate-500 mt-1">Daftar unit dan lokasi proyek yang menjadi tanggung jawab Anda.</p>
      </div>

      {apiError && <Alert variant="danger" title="Gagal Memuat Penugasan">{apiError}</Alert>}

      {isLoading ? (
        <div className="space-y-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-3">
              <Skeleton className="h-5 w-40" />
              <Skeleton className="h-4 w-64" />
            </Card>
          ))}
        </div>
      ) : assignments.length > 0 ? (
        <div className="space-y-4">
          {assignments.map((a) => (
            <Card key={a.id} className="p-5">
              <Link to={`/operator/assignments/${a.id}`} className="flex items-start justify-between gap-3">
                <div className="space-y-2 min-w-0">
                  <div className="flex items-center gap-2 flex-wrap">
                    <span className="font-mono text-sm font-bold text-slate-900">{a.booking_code ?? `Rental #${a.rental_id}`}</span>
                    <Badge variant="success" size="sm">{a.status}</Badge>
                    {a.rental_status && <Badge variant="secondary" size="sm">{a.rental_status}</Badge>}
                  </div>
                  <p className="text-sm text-slate-700 flex items-center gap-1.5">
                    <Truck size={14} className="text-slate-400" /> {a.unit?.serial_number ?? '-'}
                    {a.unit?.plate_number ? ` (${a.unit.plate_number})` : ''}
                  </p>
                  <p className="text-xs text-slate-500 flex items-center gap-1.5">
                    <MapPin size={12} /> {a.project_location?.project_name ?? '-'}
                    {a.project_location?.city ? ` - ${a.project_location.city}` : ''}
                  </p>
                </div>
                <ChevronRight size={18} className="text-slate-300 shrink-0 mt-1" />
              </Link>
            </Card>
          ))}
        </div>
      ) : (
        <EmptyState
          icon={<ClipboardList className="w-12 h-12" />}
          title="Belum Ada Penugasan"
          description="Anda belum ditugaskan pada unit rental mana pun saat ini."
        />
      )}
    </div>
  )
}

export default OperatorAssignmentsPage
