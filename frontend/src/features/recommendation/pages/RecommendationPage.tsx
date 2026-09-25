import React, { useState, useEffect } from 'react'
import { Link } from 'react-router-dom'
import { Sparkles, History, RotateCcw, ArrowRight, Layers, ShieldCheck, Tag, Info } from 'lucide-react'
import { recommendationService } from '../services/recommendationService'
import type { RecommendationRequest, CreateRecommendationInput } from '@/types/recommendation'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Skeleton } from '@/components/ui/Skeleton'
import { Alert } from '@/components/feedback/Alert'
import { EmptyState } from '@/components/feedback/EmptyState'
import { useToast } from '@/hooks/useToast'

export const RecommendationPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [activeTab, setActiveTab] = useState<'calculator' | 'history'>('calculator')

  // Form State
  const [formData, setFormData] = useState<CreateRecommendationInput>({
    project_type: '',
    terrain_condition: '',
    load_capacity: undefined,
    work_volume: undefined,
    depth_requirement: undefined,
    reach_requirement: undefined,
    duration_days: undefined,
    budget_range: '',
  })

  const [errors, setErrors] = useState<Record<string, string>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [apiError, setApiError] = useState<string | null>(null)
  const [currentResult, setCurrentResult] = useState<RecommendationRequest | null>(null)

  // History State
  const [historyList, setHistoryList] = useState<RecommendationRequest[]>([])
  const [isLoadingHistory, setIsLoadingHistory] = useState(false)

  const loadHistory = async () => {
    setIsLoadingHistory(true)
    try {
      const res = await recommendationService.getHistory(1, 10)
      if (res.success && res.data) {
        setHistoryList(res.data)
      }
    } catch {
      showErrorToast('Gagal memuat riwayat rekomendasi.')
    } finally {
      setIsLoadingHistory(false)
    }
  }

  useEffect(() => {
    if (activeTab === 'history') {
      loadHistory()
    }
  }, [activeTab])

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {}
    if (!formData.project_type.trim()) {
      newErrors.project_type = 'Jenis pekerjaan/proyek wajib dipilih atau diisi.'
    }
    if (!formData.terrain_condition.trim()) {
      newErrors.terrain_condition = 'Kondisi tanah/medan lokasi wajib dipilih atau diisi.'
    }
    if (formData.load_capacity !== undefined && formData.load_capacity < 0) {
      newErrors.load_capacity = 'Kapasitas muat tidak boleh bernilai negatif.'
    }
    if (formData.duration_days !== undefined && formData.duration_days < 1) {
      newErrors.duration_days = 'Durasi sewa minimal 1 hari.'
    }

    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validateForm()) return

    setIsSubmitting(true)
    setApiError(null)

    try {
      const res = await recommendationService.requestRecommendation(formData)
      if (res.success && res.data) {
        setCurrentResult(res.data)
        showSuccessToast('Rekomendasi armada berhasil diproses.')
      } else {
        setApiError(res.message || 'Gagal memproses rekomendasi.')
      }
    } catch (err: any) {
      setApiError(err?.response?.data?.message || 'Terjadi kesalahan sistem saat memproses rekomendasi.')
    } finally {
      setIsSubmitting(false)
    }
  }

  const handleReset = () => {
    setFormData({
      project_type: '',
      terrain_condition: '',
      load_capacity: undefined,
      work_volume: undefined,
      depth_requirement: undefined,
      reach_requirement: undefined,
      duration_days: undefined,
      budget_range: '',
    })
    setErrors({})
    setCurrentResult(null)
    setApiError(null)
  }

  const formatRupiah = (val: number) => {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      maximumFractionDigits: 0,
    }).format(val)
  }

  return (
    <div className="space-y-6 max-w-5xl mx-auto">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <Sparkles className="text-primary-600" size={24} />
            Sistem Rekomendasi Armada Alat Berat
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Asisten cerdas berbasis aturan untuk menentukan tipe dan model armada alat berat yang paling presisi.
          </p>
        </div>

        {/* Tab Switcher */}
        <div className="flex rounded-lg border border-slate-200 bg-white p-1 self-start sm:self-auto">
          <button
            type="button"
            onClick={() => setActiveTab('calculator')}
            className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
              activeTab === 'calculator'
                ? 'bg-primary-600 text-white shadow-sm'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <Sparkles size={14} />
            Kalkulator
          </button>
          <button
            type="button"
            onClick={() => setActiveTab('history')}
            className={`flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded-md transition-colors ${
              activeTab === 'history'
                ? 'bg-primary-600 text-white shadow-sm'
                : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            <History size={14} />
            Riwayat
          </button>
        </div>
      </div>

      {activeTab === 'calculator' ? (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
          {/* Left Panel: Form Input */}
          <div className="lg:col-span-5 space-y-6">
            <Card className="p-6">
              <form onSubmit={handleSubmit} className="space-y-5">
                <div className="border-b border-slate-100 pb-3">
                  <h3 className="text-base font-semibold text-slate-900">Input Kriteria Proyek</h3>
                  <p className="text-xs text-slate-500">Masukkan spesifikasi dan medan proyek Anda</p>
                </div>

                {/* Project Type */}
                <div>
                  <Select
                    label="Jenis Pekerjaan / Proyek *"
                    value={formData.project_type}
                    onChange={(e) => setFormData({ ...formData, project_type: e.target.value })}
                    error={errors.project_type}
                  >
                    <option value="">-- Pilih Jenis Pekerjaan --</option>
                    <option value="Galian Basah dan Drainase">Galian Basah & Drainase</option>
                    <option value="Konstruksi Jalan & Perataan">Konstruksi Jalan & Perataan</option>
                    <option value="Land Clearing & Pembukaan Lahan">Land Clearing & Pembukaan Lahan</option>
                    <option value="Pertambangan / Galian Keras">Pertambangan & Bebatuan Keras</option>
                    <option value="Pondasi & Sipil Gedung">Pondasi & Sipil Gedung</option>
                  </Select>
                </div>

                {/* Terrain Condition */}
                <div>
                  <Select
                    label="Kondisi Tanah / Medan *"
                    value={formData.terrain_condition}
                    onChange={(e) => setFormData({ ...formData, terrain_condition: e.target.value })}
                    error={errors.terrain_condition}
                  >
                    <option value="">-- Pilih Karakteristik Medan --</option>
                    <option value="Lumpur / Rawa / Basah">Lumpur / Rawa / Basah</option>
                    <option value="Tanah Keras / Bebatuan / Tambang">Tanah Keras / Bebatuan / Tambang</option>
                    <option value="Aspal / Datar / Gravel">Aspal / Datar / Gravel</option>
                    <option value="Tanah Normal / Standar">Tanah Normal / Standar</option>
                  </Select>
                </div>

                {/* Capacity & Volume Grid */}
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label="Target Beban (Ton)"
                    type="number"
                    step="0.1"
                    placeholder="Contoh: 20"
                    value={formData.load_capacity ?? ''}
                    onChange={(e) => setFormData({ ...formData, load_capacity: e.target.value ? parseFloat(e.target.value) : undefined })}
                    error={errors.load_capacity}
                  />
                  <Input
                    label="Volume Work (m³)"
                    type="number"
                    step="1"
                    placeholder="Contoh: 5000"
                    value={formData.work_volume ?? ''}
                    onChange={(e) => setFormData({ ...formData, work_volume: e.target.value ? parseFloat(e.target.value) : undefined })}
                  />
                </div>

                {/* Depth & Reach Grid */}
                <div className="grid grid-cols-2 gap-3">
                  <Input
                    label="Kedalaman (m)"
                    type="number"
                    step="0.1"
                    placeholder="Contoh: 4.5"
                    value={formData.depth_requirement ?? ''}
                    onChange={(e) => setFormData({ ...formData, depth_requirement: e.target.value ? parseFloat(e.target.value) : undefined })}
                  />
                  <Input
                    label="Jangkauan (m)"
                    type="number"
                    step="0.1"
                    placeholder="Contoh: 9.5"
                    value={formData.reach_requirement ?? ''}
                    onChange={(e) => setFormData({ ...formData, reach_requirement: e.target.value ? parseFloat(e.target.value) : undefined })}
                  />
                </div>

                {/* Duration */}
                <Input
                  label="Estimasi Durasi (Hari)"
                  type="number"
                  placeholder="Contoh: 14"
                  value={formData.duration_days ?? ''}
                  onChange={(e) => setFormData({ ...formData, duration_days: e.target.value ? parseInt(e.target.value, 10) : undefined })}
                  error={errors.duration_days}
                />

                {/* Form Actions */}
                <div className="flex gap-2 pt-2">
                  <Button
                    type="submit"
                    variant="primary"
                    className="flex-1 gap-2"
                    isLoading={isSubmitting}
                  >
                    <Sparkles size={16} />
                    Dapatkan Rekomendasi
                  </Button>
                  <Button
                    type="button"
                    variant="outline"
                    onClick={handleReset}
                    disabled={isSubmitting}
                  >
                    <RotateCcw size={16} />
                  </Button>
                </div>
              </form>
            </Card>
          </div>

          {/* Right Panel: Results Display */}
          <div className="lg:col-span-7 space-y-4">
            {apiError && (
              <Alert variant="danger" title="Gagal Memproses Rekomendasi">
                {apiError}
              </Alert>
            )}

            {isSubmitting ? (
              <div className="space-y-4">
                <Card className="p-6">
                  <div className="flex items-center gap-4 mb-4">
                    <Skeleton className="w-16 h-16 rounded-xl" />
                    <div className="space-y-2 flex-1">
                      <Skeleton className="h-5 w-1/3" />
                      <Skeleton className="h-4 w-1/2" />
                    </div>
                  </div>
                  <Skeleton className="h-16 w-full rounded-lg" />
                </Card>
                <Card className="p-6">
                  <div className="flex items-center gap-4 mb-4">
                    <Skeleton className="w-16 h-16 rounded-xl" />
                    <div className="space-y-2 flex-1">
                      <Skeleton className="h-5 w-1/3" />
                      <Skeleton className="h-4 w-1/2" />
                    </div>
                  </div>
                  <Skeleton className="h-16 w-full rounded-lg" />
                </Card>
              </div>
            ) : currentResult ? (
              <div className="space-y-5">
                <div className="flex items-center justify-between">
                  <h3 className="text-lg font-bold text-slate-900 flex items-center gap-2">
                    Hasil Analisis Rekomendasi
                    <Badge variant="success" className="text-xs">
                      {currentResult.results?.length ?? 0} Armada Terpilih
                    </Badge>
                  </h3>
                  <span className="text-xs text-slate-500">
                    Selesai diproses
                  </span>
                </div>

                {currentResult.results && currentResult.results.length > 0 ? (
                  currentResult.results.map((item) => {
                    const model = item.model
                    const photo = model?.attachments?.find((a) => a.document_type === 'EQUIPMENT_PHOTO')
                    const minPrice = model?.prices && model.prices.length > 0 ? Math.min(...model.prices.map((p) => p.base_rate)) : null

                    return (
                      <Card key={item.id} className="p-6 transition-all border-slate-200 hover:border-primary-300 hover:shadow-md">
                        <div className="flex flex-col sm:flex-row gap-4">
                          {/* Image Thumbnail */}
                          <div className="w-full sm:w-32 h-28 bg-slate-100 rounded-xl overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                            {photo?.url ? (
                              <img src={photo.url} alt={model?.model_name} className="w-full h-full object-cover" loading="lazy" />
                            ) : (
                              <Layers className="text-slate-400" size={32} />
                            )}
                          </div>

                          {/* Content Details */}
                          <div className="flex-1 space-y-3">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                              <div>
                                <div className="flex items-center gap-2">
                                  <Badge variant={item.rank === 1 ? 'default' : 'secondary'} className="font-bold">
                                    #{item.rank ?? 1} Pilihan
                                  </Badge>
                                  <span className="text-xs font-semibold text-slate-500 uppercase tracking-wider">{model?.brand}</span>
                                </div>
                                <h4 className="text-lg font-bold text-slate-900 mt-1">
                                  {model?.brand} {model?.model_name}
                                </h4>
                              </div>

                              <div className="text-right">
                                <Badge variant={item.match_score >= 90 ? 'success' : 'warning'} className="text-sm px-2.5 py-1 font-bold">
                                  {item.match_score}% Cocok
                                </Badge>
                              </div>
                            </div>

                            {/* Specs & Pricing summary */}
                            <div className="flex flex-wrap items-center gap-4 text-xs text-slate-600 bg-slate-50 p-2.5 rounded-lg border border-slate-100">
                              <span className="flex items-center gap-1 font-medium">
                                <ShieldCheck size={14} className="text-emerald-600" />
                                Kapasitas: {model?.capacity_value} {model?.capacity_unit}
                              </span>
                              {minPrice !== null && (
                                <span className="flex items-center gap-1 font-medium text-slate-900">
                                  <Tag size={14} className="text-primary-600" />
                                  Tarif Mulai: {formatRupiah(minPrice)}/jam
                                </span>
                              )}
                              <span className="text-slate-500">
                                Unit Fisik Terdaftar: {model?.units_count ?? 0}
                              </span>
                            </div>

                            {/* Explanation Traceability */}
                            <div className="p-3 bg-amber-50/60 rounded-lg border border-amber-200/60 text-xs text-slate-700 space-y-1">
                              <div className="font-semibold text-amber-900 flex items-center gap-1">
                                <Info size={13} className="text-amber-600 shrink-0" />
                                Alasan Rekomendasi:
                              </div>
                              <p className="leading-relaxed">{item.reasoning_text}</p>
                            </div>

                            {/* Action: Link to Detail (NO AUTO BOOKING) */}
                            <div className="flex items-center justify-between pt-1">
                              <span className="text-[11px] text-slate-400 italic">
                                *Penyewaan dilakukan mandiri melalui katalog.
                              </span>
                              <Link to={`/app/equipment/${model?.id}`}>
                                <Button variant="outline" size="sm" className="gap-1 text-xs">
                                  Lihat Detail Armada
                                  <ArrowRight size={14} />
                                </Button>
                              </Link>
                            </div>
                          </div>
                        </div>
                      </Card>
                    )
                  })
                ) : (
                  <EmptyState
                    title="Tidak Ada Armada Terpilih"
                    description="Tidak ditemukan model alat berat yang memenuhi ambang batas kriteria. Coba atur ulang parameter beban atau medan."
                  />
                )}
              </div>
            ) : (
              <EmptyState
                title="Kalkulator Rekomendasi Siap Digunakan"
                description="Isi kriteria jenis pekerjaan dan kondisi tanah di formulir sebelah kiri untuk memulai analisa rekomendasi armada."
              />
            )}
          </div>
        </div>
      ) : (
        /* History View */
        <div className="space-y-4">
          <h3 className="text-lg font-bold text-slate-900">Riwayat Permintaan Rekomendasi</h3>
          {isLoadingHistory ? (
            <Card className="p-6 space-y-3">
              <Skeleton className="h-6 w-1/4" />
              <Skeleton className="h-4 w-1/2" />
            </Card>
          ) : historyList.length > 0 ? (
            historyList.map((req) => (
              <Card key={req.id} className="p-5 space-y-3">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <Badge variant="secondary">Request #{req.id}</Badge>
                    <span className="text-xs text-slate-500">
                      {new Date(req.created_at).toLocaleDateString('id-ID', {
                        day: 'numeric',
                        month: 'long',
                        year: 'numeric',
                      })}
                    </span>
                  </div>
                  <Badge variant="success">{req.status}</Badge>
                </div>

                <div className="text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 flex flex-wrap gap-4">
                  <span>Proyek: <strong>{req.criteria?.project_type}</strong></span>
                  <span>Medan: <strong>{req.criteria?.terrain_condition}</strong></span>
                  {req.criteria?.load_capacity && (
                    <span>Beban: <strong>{req.criteria.load_capacity} Ton</strong></span>
                  )}
                </div>

                {req.results && req.results.length > 0 && (
                  <div className="text-xs text-slate-500 pt-1">
                    Armada Utama Ditampilkan: <strong>{req.results[0]?.model?.brand} {req.results[0]?.model?.model_name}</strong> ({req.results[0]?.match_score}% Match)
                  </div>
                )}
              </Card>
            ))
          ) : (
            <EmptyState
              title="Belum Ada Riwayat"
              description="Anda belum pernah mengajukan analisis rekomendasi alat berat."
            />
          )}
        </div>
      )}
    </div>
  )
}
export default RecommendationPage
