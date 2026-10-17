import React, { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { LayoutDashboard, Truck, MapPin, ChevronRight } from 'lucide-react'
import { useAuth } from '@/hooks/useAuth'
import { operatorService } from '../services/operatorService'
import type { OperatorDashboard } from '../services/operatorService'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'

export const OperatorDashboardPage: React.FC = () => {
  const { user } = useAuth()
  const [data, setData] = useState<OperatorDashboard | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  useEffect(() => {
    let mounted = true
    operatorService
      .getDashboard()
      .then((res) => {
        if (mounted && res.success && res.data) setData(res.data)
      })
      .catch((err) => {
        if (mounted) setApiError(err?.message || 'Gagal memuat dashboard.')
      })
      .finally(() => {
        if (mounted) setIsLoading(false)
      })
    return () => {
      mounted = false
    }
  }, [])

  return (
    <div className="space-y-6 max-w-7xl">
      <section className="rounded-2xl border border-accent-300 bg-accent-300 p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-xl font-bold text-slate-900 tracking-tight">Selamat Datang, {user?.name || 'Operator'}</h2>
          <p className="text-sm text-slate-800 mt-1">Lihat unit dan lokasi proyek yang menjadi tanggung jawab Anda.</p>
        </div>
        <span className="hidden sm:flex h-11 w-11 items-center justify-center rounded-lg bg-accent-500 text-slate-900 shrink-0">
          <LayoutDashboard size={20} />
        </span>
      </section>

      {apiError && <Alert variant="danger" title="Gagal Memuat Dashboard">{apiError}</Alert>}

      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          {[1, 2].map((i) => (
            <Card key={i} className="p-5 space-y-3">
              <Skeleton className="h-3 w-24" />
              <Skeleton className="h-7 w-16" />
            </Card>
          ))}
        </div>
      ) : (
        <>
          <section className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="card-surface p-5">
              <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Penugasan Aktif</p>
              <p className="text-[26px] font-bold tracking-tight mt-2 text-slate-900">{data?.active_assignments ?? 0}</p>
            </div>
            <div className="card-surface p-5">
              <p className="text-[11px] font-semibold uppercase tracking-wider text-slate-400">Rental Aktif</p>
              <p className="text-[26px] font-bold tracking-tight mt-2 text-emerald-600">{data?.active_rentals ?? 0}</p>
            </div>
          </section>

          <Card className="card-surface">
            <div className="flex items-center justify-between px-5 py-4 border-b border-slate-100">
              <h3 className="font-semibold text-slate-900">Penugasan Saya</h3>
              <Link to="/operator/assignments" className="text-sm font-medium text-primary-700 hover:text-primary-800 inline-flex items-center gap-1">
                Lihat Semua <ChevronRight size={14} />
              </Link>
            </div>
            {data && data.assignments.length > 0 ? (
              <div className="divide-y divide-slate-50">
                {data.assignments.map((a) => (
                  <Link key={a.id} to={`/operator/assignments/${a.id}`} className="flex items-center justify-between gap-3 px-5 py-3 text-sm hover:bg-slate-50">
                    <div className="min-w-0">
                      <p className="truncate font-medium text-slate-800 flex items-center gap-1.5">
                        <Truck size={14} className="text-slate-400" /> {a.unit?.serial_number ?? `Unit #${a.id}`}
                      </p>
                      <p className="text-xs text-slate-400 flex items-center gap-1 mt-0.5">
                        <MapPin size={11} /> {a.project_location?.project_name ?? '-'} {a.project_location?.city ? `- ${a.project_location.city}` : ''}
                      </p>
                    </div>
                    <Badge variant="success" size="sm">{a.status}</Badge>
                  </Link>
                ))}
              </div>
            ) : (
              <p className="px-5 py-6 text-sm text-slate-400">Belum ada penugasan aktif.</p>
            )}
          </Card>
        </>
      )}
    </div>
  )
}

export default OperatorDashboardPage
