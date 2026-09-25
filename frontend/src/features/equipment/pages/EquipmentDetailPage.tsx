import React, { useState, useEffect } from 'react'
import { useParams, Link } from 'react-router-dom'
import { ArrowLeft, Layers, ShieldCheck, Clock, Check, Truck } from 'lucide-react'
import { Badge } from '@/components/ui/Badge'
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/Card'
import { LoadingState } from '@/components/feedback/LoadingState'
import { ErrorState } from '@/components/feedback/ErrorState'
import { equipmentService } from '../services/equipmentService'
import type { EquipmentModel } from '@/types/equipment'

export const EquipmentDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>()

  const [model, setModel] = useState<EquipmentModel | null>(null)
  const [activePhotoIdx, setActivePhotoIdx] = useState(0)
  const [isLoading, setIsLoading] = useState(true)
  const [isError, setIsError] = useState(false)

  useEffect(() => {
    const fetchDetail = async () => {
      if (!id) return
      setIsLoading(true)
      setIsError(false)
      try {
        const data = await equipmentService.getModel(Number(id))
        setModel(data)
      } catch {
        setIsError(true)
      } finally {
        setIsLoading(false)
      }
    }

    fetchDetail()
  }, [id])

  if (isLoading) {
    return <LoadingState message="Memuat spesifikasi armada..." className="min-h-[60vh]" />
  }

  if (isError || !model) {
    return (
      <ErrorState
        message="Model alat berat tidak ditemukan atau tidak aktif."
        onRetry={() => window.location.reload()}
      />
    )
  }

  const allInPrice = model.prices?.find((p) => p.is_all_in)
  const nonAllInPrice = model.prices?.find((p) => !p.is_all_in)

  const photos = model.attachments?.map((a) => a.url) || []

  return (
    <div className="space-y-6">
      {/* Back Button */}
      <div>
        <Link to="/app/equipment" className="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 hover:text-slate-900 transition-colors">
          <ArrowLeft size={16} /> Kembali ke Katalog
        </Link>
      </div>

      {/* Main Grid: Photos & Specs */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8">
        {/* Left: Gallery (5 cols) */}
        <div className="lg:col-span-6 space-y-4">
          <div className="aspect-video w-full rounded-2xl bg-slate-100 border border-slate-200 overflow-hidden flex items-center justify-center relative shadow-xs">
            {photos.length > 0 ? (
              <img
                src={photos[activePhotoIdx]}
                alt={`${model.brand} ${model.model_name}`}
                className="w-full h-full object-cover"
              />
            ) : (
              <div className="flex flex-col items-center gap-2 text-slate-400">
                <Truck size={48} />
                <span className="text-sm font-medium">Foto Belum Tersedia</span>
              </div>
            )}
          </div>

          {/* Thumbnail list */}
          {photos.length > 1 && (
            <div className="flex gap-2.5 overflow-x-auto pb-1">
              {photos.map((url, idx) => (
                <button
                  key={idx}
                  onClick={() => setActivePhotoIdx(idx)}
                  className={`w-20 aspect-video rounded-lg overflow-hidden border-2 transition-all shrink-0 cursor-pointer ${
                    activePhotoIdx === idx ? 'border-primary-600 ring-2 ring-primary-100' : 'border-slate-200 opacity-70 hover:opacity-100'
                  }`}
                >
                  <img src={url} alt={`Thumbnail ${idx + 1}`} className="w-full h-full object-cover" />
                </button>
              ))}
            </div>
          )}
        </div>

        {/* Right: Specs & Info (6 cols) */}
        <div className="lg:col-span-6 space-y-6">
          <div className="space-y-2">
            <div className="flex items-center gap-2">
              <span className="inline-flex items-center gap-1 text-xs font-semibold text-primary-700 bg-primary-50 px-2.5 py-1 rounded-md border border-primary-200">
                <Layers size={13} /> {model.type?.name || 'Alat Berat'}
              </span>
              <Badge variant="success" size="sm">Siap Beroperasi</Badge>
            </div>

            <h1 className="text-3xl font-extrabold text-slate-900 tracking-tight">
              {model.brand} {model.model_name}
            </h1>
          </div>

          {/* Specifications Grid */}
          <Card>
            <CardHeader className="p-5 pb-3">
              <CardTitle className="text-base">Spesifikasi Utama Mesin</CardTitle>
            </CardHeader>
            <CardContent className="p-5 pt-0">
              <dl className="grid grid-cols-2 gap-4 text-sm">
                <div className="bg-slate-50 p-3 rounded-lg">
                  <dt className="text-xs text-slate-500 font-medium">Merk Pabrikan</dt>
                  <dd className="font-semibold text-slate-900 mt-0.5">{model.brand}</dd>
                </div>
                <div className="bg-slate-50 p-3 rounded-lg">
                  <dt className="text-xs text-slate-500 font-medium">Seri Model</dt>
                  <dd className="font-semibold text-slate-900 mt-0.5">{model.model_name}</dd>
                </div>
                <div className="bg-slate-50 p-3 rounded-lg">
                  <dt className="text-xs text-slate-500 font-medium">Nilai Kapasitas</dt>
                  <dd className="font-semibold text-slate-900 mt-0.5">{model.capacity_value} {model.capacity_unit}</dd>
                </div>
                <div className="bg-slate-50 p-3 rounded-lg">
                  <dt className="text-xs text-slate-500 font-medium">Uji Kelayakan</dt>
                  <dd className="font-semibold text-emerald-600 flex items-center gap-1 mt-0.5">
                    <ShieldCheck size={14} /> Terverifikasi BAST
                  </dd>
                </div>
              </dl>
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Pricing Comparison Section */}
      <div className="space-y-4 pt-4 border-t border-slate-200">
        <div>
          <h3 className="text-xl font-bold text-slate-900 tracking-tight">Informasi Tarif Sewa Resmi</h3>
          <p className="text-sm text-slate-500">Pilihan skema sewa fleksibel sesuai kebutuhan operasional proyek Anda</p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Non All-in Card */}
          <Card className="border-2 border-slate-200 relative">
            <CardHeader className="p-6">
              <div className="flex justify-between items-start">
                <div>
                  <Badge variant="secondary" className="mb-2">Bare Rental</Badge>
                  <CardTitle className="text-lg">Non All-in (Unit Saja)</CardTitle>
                </div>
                <p className="font-mono text-xl font-bold text-slate-900">
                  {nonAllInPrice ? `Rp ${nonAllInPrice.base_rate.toLocaleString('id-ID')}` : '-'}
                  <span className="text-xs font-normal text-slate-500"> /jam</span>
                </p>
              </div>
            </CardHeader>
            <CardContent className="p-6 pt-0 space-y-3 text-sm">
              <div className="flex items-center gap-2 text-slate-700">
                <Clock size={16} className="text-slate-400 shrink-0" />
                <span>Minimum tagihan <strong>8 jam kerja</strong> per hari</span>
              </div>
              <div className="flex items-center gap-2 text-slate-700">
                <Check size={16} className="text-emerald-500 shrink-0" />
                <span>Hak guna pakai aset fisik unit murni</span>
              </div>
              <div className="flex items-center gap-2 text-slate-500 text-xs bg-slate-50 p-2.5 rounded-lg">
                <span>Solar (BBM), upah dan akomodasi operator disediakan oleh penyewa di lokasi.</span>
              </div>
            </CardContent>
          </Card>

          {/* All-in Card */}
          <Card className="border-2 border-primary-600 relative bg-primary-50/20 shadow-xs">
            <div className="absolute top-0 right-6 -translate-y-1/2">
              <Badge variant="default" className="bg-primary-600 text-white border-none px-3 py-1 font-semibold">
                Paling Praktis
              </Badge>
            </div>
            <CardHeader className="p-6">
              <div className="flex justify-between items-start">
                <div>
                  <Badge variant="default" className="mb-2">Paket Lengkap</Badge>
                  <CardTitle className="text-lg">All-in (Unit + BBM + Operator)</CardTitle>
                </div>
                <p className="font-mono text-xl font-bold text-primary-700">
                  {allInPrice ? `Rp ${allInPrice.base_rate.toLocaleString('id-ID')}` : '-'}
                  <span className="text-xs font-normal text-slate-500"> /jam</span>
                </p>
              </div>
            </CardHeader>
            <CardContent className="p-6 pt-0 space-y-3 text-sm">
              <div className="flex items-center gap-2 text-slate-700">
                <Clock size={16} className="text-primary-600 shrink-0" />
                <span>Minimum tagihan <strong>8 jam kerja</strong> per hari</span>
              </div>
              <div className="flex items-center gap-2 text-slate-700">
                <Check size={16} className="text-emerald-500 shrink-0" />
                <span>Termasuk honor operator berpengalaman</span>
              </div>
              <div className="flex items-center gap-2 text-slate-700">
                <Check size={16} className="text-emerald-500 shrink-0" />
                <span>Termasuk bahan bakar solar operasional</span>
              </div>
              <div className="flex items-center gap-2 text-slate-700">
                <Check size={16} className="text-emerald-500 shrink-0" />
                <span>Termasuk perawatan harian di lapangan</span>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
export default EquipmentDetailPage
