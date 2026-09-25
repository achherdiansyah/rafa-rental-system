import React, { useState, useEffect, useCallback } from 'react'
import { Link } from 'react-router-dom'
import { Search, Layers, ArrowRight, ShieldCheck, Truck } from 'lucide-react'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Card, CardHeader, CardTitle, CardContent, CardFooter } from '@/components/ui/Card'
import { Pagination } from '@/components/data-display/Pagination'
import { CardSkeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'
import { useDebounce } from '@/hooks/useDebounce'
import { equipmentService } from '../services/equipmentService'
import type { EquipmentModel, EquipmentType } from '@/types/equipment'
import type { PaginationMeta } from '@/types/api'

export const EquipmentCatalogPage: React.FC = () => {
  const { error: toastError } = useToast()

  const [models, setModels] = useState<EquipmentModel[]>([])
  const [typesList, setTypesList] = useState<EquipmentType[]>([])
  const [meta, setMeta] = useState<PaginationMeta>({ current_page: 1, per_page: 9, total: 0, last_page: 1 })
  const [search, setSearch] = useState('')
  const debouncedSearch = useDebounce(search, 300)
  const [filterType, setFilterType] = useState('')
  const [filterBrand, setFilterBrand] = useState('')
  const [isLoading, setIsLoading] = useState(true)

  const fetchTypes = useCallback(async () => {
    try {
      const res = await equipmentService.getTypes(undefined, true)
      setTypesList((res.data as unknown as EquipmentType[]) || [])
    } catch {
      // Ignore
    }
  }, [])

  const fetchModels = useCallback(async (page: number = 1) => {
    setIsLoading(true)
    try {
      const res = await equipmentService.getModels({
        page,
        per_page: 9,
        search: debouncedSearch || undefined,
        equipment_type_id: filterType ? Number(filterType) : undefined,
        brand: filterBrand || undefined,
        is_active: true, // Only show active models to users
      })
      setModels(res.data || [])
      if (res.meta) setMeta(res.meta)
    } catch {
      toastError('Gagal memuat katalog armada alat berat.')
    } finally {
      setIsLoading(false)
    }
  }, [debouncedSearch, filterType, filterBrand, toastError])

  useEffect(() => {
    fetchTypes()
  }, [fetchTypes])

  useEffect(() => {
    fetchModels(1)
  }, [fetchModels])

  const getLowestPrice = (model: EquipmentModel): number | null => {
    if (!model.prices || model.prices.length === 0) return null
    return Math.min(...model.prices.map((p) => p.base_rate))
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div>
        <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Katalog Armada Alat Berat</h2>
        <p className="text-sm text-slate-500">
          Jelajahi spesifikasi alat berat, ketersediaan unit, dan informasi tarif sewa resmi
        </p>
      </div>

      {/* Filter Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
          <Input
            placeholder="Cari jenis alat atau merk..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="pl-9"
          />
        </div>

        <div className="flex gap-2">
          <Select
            value={filterType}
            onChange={(e) => setFilterType(e.target.value)}
            className="w-48"
          >
            <option value="">Semua Kategori</option>
            {typesList.map((t) => (
              <option key={t.id} value={t.id}>{t.name}</option>
            ))}
          </Select>

          <Select
            value={filterBrand}
            onChange={(e) => setFilterBrand(e.target.value)}
            className="w-40"
          >
            <option value="">Semua Merk</option>
            <option value="Komatsu">Komatsu</option>
            <option value="Caterpillar">Caterpillar</option>
            <option value="Kobelco">Kobelco</option>
            <option value="Hitachi">Hitachi</option>
            <option value="Dynapac">Dynapac</option>
          </Select>
        </div>
      </div>

      {/* Catalog Cards Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {Array.from({ length: 6 }).map((_, i) => (
            <CardSkeleton key={i} />
          ))}
        </div>
      ) : models.length === 0 ? (
        <EmptyState
          icon={<Truck size={28} />}
          title="Armada Tidak Ditemukan"
          description="Tidak ditemukan model alat berat yang sesuai dengan filter pencarian Anda."
        />
      ) : (
        <>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            {models.map((model) => {
              const lowestRate = getLowestPrice(model)
              const primaryPhoto = model.attachments?.[0]?.url

              return (
                <Card key={model.id} className="overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
                  {/* Image / Thumbnail */}
                  <div className="aspect-video w-full bg-slate-100 relative overflow-hidden border-b border-slate-100 flex items-center justify-center">
                    {primaryPhoto ? (
                      <img
                        src={primaryPhoto}
                        alt={`${model.brand} ${model.model_name}`}
                        loading="lazy"
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <div className="flex flex-col items-center gap-1.5 text-slate-400">
                        <Truck size={32} />
                        <span className="text-xs">Foto Belum Tersedia</span>
                      </div>
                    )}
                    <div className="absolute top-2.5 right-2.5">
                      <Badge variant="secondary" size="sm" className="bg-white/90 backdrop-blur-xs font-semibold">
                        {model.capacity_value} {model.capacity_unit}
                      </Badge>
                    </div>
                  </div>

                  {/* Body Content */}
                  <CardHeader className="p-5 pb-2">
                    <div className="flex items-center gap-2 mb-1">
                      <span className="inline-flex items-center gap-1 text-xs font-medium text-slate-500 bg-slate-100 px-2 py-0.5 rounded">
                        <Layers size={11} /> {model.type?.name || 'Alat Berat'}
                      </span>
                    </div>
                    <CardTitle className="text-lg">
                      {model.brand} {model.model_name}
                    </CardTitle>
                  </CardHeader>

                  <CardContent className="px-5 py-2 space-y-2">
                    <div className="flex items-center justify-between text-xs text-slate-500 border-t border-slate-100 pt-2.5">
                      <span>Status Kesiapan:</span>
                      <span className="font-semibold text-emerald-600 flex items-center gap-1">
                        <ShieldCheck size={13} /> Armada Siap Sewa
                      </span>
                    </div>
                  </CardContent>

                  <CardFooter className="p-5 pt-3 border-t border-slate-100 flex items-center justify-between bg-slate-50/50">
                    <div>
                      <p className="text-xs text-slate-400">Mulai dari</p>
                      <p className="text-sm font-bold text-slate-900 font-mono">
                        {lowestRate !== null ? `Rp ${lowestRate.toLocaleString('id-ID')}/jam` : 'Hubungi Admin'}
                      </p>
                    </div>

                    <Link to={`/app/equipment/${model.id}`}>
                      <Button size="sm" rightIcon={<ArrowRight size={14} />}>
                        Lihat Detail
                      </Button>
                    </Link>
                  </CardFooter>
                </Card>
              )
            })}
          </div>

          <Pagination
            currentPage={meta.current_page}
            totalPages={meta.last_page}
            onPageChange={(p) => fetchModels(p)}
          />
        </>
      )}
    </div>
  )
}
export default EquipmentCatalogPage
