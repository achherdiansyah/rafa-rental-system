import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShoppingCart, Truck } from 'lucide-react'
import { cartService } from '../services/cartService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import type { ProjectLocation } from '@/types/projectLocation'
import type { EquipmentModel } from '@/types/equipment'
import { Modal } from '@/components/ui/Modal'
import { Button } from '@/components/ui/Button'
import { Input } from '@/components/form/Input'
import { Select } from '@/components/form/Select'
import { Alert } from '@/components/feedback/Alert'
import { useToast } from '@/hooks/useToast'

interface AddToCartModalProps {
  isOpen: boolean
  onClose: () => void
  model: EquipmentModel
  initialIsAllIn?: boolean
}

type DateValidError = {
  start?: string
  end?: string
  quantity?: string
  location?: string
}

export const AddToCartModal: React.FC<AddToCartModalProps> = ({
  isOpen,
  onClose,
  model,
  initialIsAllIn = false,
}) => {
  const navigate = useNavigate()
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [scheme, setScheme] = useState<boolean>(initialIsAllIn)
  const [quantity, setQuantity] = useState(1)
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [projectLocationId, setProjectLocationId] = useState('')

  const [locations, setLocations] = useState<ProjectLocation[]>([])
  const [isLoadingLocations, setIsLoadingLocations] = useState(false)
  const [isSubmitting, setIsSubmitting] = useState(false)
  const [errors, setErrors] = useState<DateValidError>({})

  useEffect(() => {
    if (!isOpen) return

    // Reset state each time the modal opens
    setScheme(initialIsAllIn)
    setQuantity(1)
    setStartDate('')
    setEndDate('')
    setProjectLocationId('')
    setErrors({})

    // Load user's project locations for select
    const loadLocations = async () => {
      setIsLoadingLocations(true)
      try {
        const res = await projectLocationService.getLocations(1, 100)
        if (res.success && res.data) {
          setLocations(res.data)
        }
      } catch {
        setLocations([])
      } finally {
        setIsLoadingLocations(false)
      }
    }

    loadLocations()
  }, [isOpen, initialIsAllIn])

  const validate = (): boolean => {
    const next: DateValidError = {}

    if (!quantity || quantity < 1) {
      next.quantity = 'Jumlah unit minimal 1.'
    }

    const today = new Date().toISOString().split('T')[0]
    if (!startDate) {
      next.start = 'Tanggal mulai wajib diisi.'
    } else if (startDate < today) {
      next.start = 'Tanggal mulai tidak boleh sebelum hari ini.'
    }

    if (!endDate) {
      next.end = 'Tanggal selesai wajib diisi.'
    } else if (startDate && endDate < startDate) {
      next.end = 'Tanggal selesai tidak boleh sebelum tanggal mulai.'
    }

    setErrors(next)
    return Object.keys(next).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validate()) return

    setIsSubmitting(true)
    try {
      await cartService.addItem({
        equipment_model_id: model.id,
        quantity,
        is_all_in: scheme,
        start_date: startDate,
        end_date: endDate,
        project_location_id: projectLocationId ? Number(projectLocationId) : undefined,
      })
      showSuccessToast('Armada berhasil ditambahkan ke keranjang sewa.')
      onClose()
    } catch (err: any) {
      const msg = err?.message || 'Terjadi kesalahan saat menambahkan armada ke keranjang.'
      showErrorToast(msg)
    } finally {
      setIsSubmitting(false)
    }
  }

  const selectedPrice = model.prices?.find((p) => p.is_all_in === scheme)
  const rateLabel = selectedPrice
    ? `Rp ${selectedPrice.base_rate.toLocaleString('id-ID')} / jam`
    : 'Tarif belum tersedia'

  return (
    <Modal
      isOpen={isOpen}
      onClose={() => !isSubmitting && onClose()}
      title={`Sewa ${model.brand} ${model.model_name}`}
      description="Tentukan kebutuhan sewa armada Anda. Jumlah unit fisik ditetapkan oleh Admin saat persetujuan booking."
      size="md"
    >
      <form onSubmit={handleSubmit} className="space-y-5">
        {/* Scheme Selector */}
        <div>
          <span className="block text-sm font-medium text-slate-700 mb-2">Pilih Skema Sewa</span>
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
            <button
              type="button"
              onClick={() => setScheme(false)}
              className={`rounded-xl border-2 p-3 text-left transition-all cursor-pointer ${
                !scheme ? 'border-primary-600 bg-primary-50/50' : 'border-slate-200 hover:border-slate-300'
              }`}
            >
              <span className="block text-sm font-semibold text-slate-900">Non All-in</span>
              <span className="text-xs text-slate-500">Bare Rental (Unit Saja)</span>
              <span className="block text-sm font-bold text-slate-900 mt-1">
                {model.prices?.some((p) => !p.is_all_in)
                  ? `Rp ${model.prices.find((p) => !p.is_all_in)?.base_rate.toLocaleString('id-ID')} /jam`
                  : '-'}
              </span>
            </button>
            <button
              type="button"
              onClick={() => setScheme(true)}
              className={`rounded-xl border-2 p-3 text-left transition-all cursor-pointer ${
                scheme ? 'border-primary-600 bg-primary-50/50' : 'border-slate-200 hover:border-slate-300'
              }`}
            >
              <span className="block text-sm font-semibold text-slate-900">All-in</span>
              <span className="text-xs text-slate-500">Unit + BBM + Operator</span>
              <span className="block text-sm font-bold text-primary-700 mt-1">
                {model.prices?.some((p) => p.is_all_in)
                  ? `Rp ${model.prices.find((p) => p.is_all_in)?.base_rate.toLocaleString('id-ID')} /jam`
                  : '-'}
              </span>
            </button>
          </div>
        </div>

        {/* Quantity & Dates */}
        <Input
          label="Jumlah Unit (Kuota)"
          type="number"
          min={1}
          step={1}
          value={quantity}
          onChange={(e) => setQuantity(parseInt(e.target.value || '0', 10))}
          error={errors.quantity}
          disabled={isSubmitting}
        />

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <Input
            label="Tanggal Mulai Sewa *"
            type="date"
            value={startDate}
            onChange={(e) => setStartDate(e.target.value)}
            error={errors.start}
            disabled={isSubmitting}
          />
          <Input
            label="Tanggal Selesai Sewa *"
            type="date"
            value={endDate}
            onChange={(e) => setEndDate(e.target.value)}
            error={errors.end}
            disabled={isSubmitting}
          />
        </div>

        {/* Project Location */}
        <div>
          <Select
            label="Lokasi Proyek"
            value={projectLocationId}
            onChange={(e) => setProjectLocationId(e.target.value)}
            error={errors.location}
            disabled={isSubmitting || isLoadingLocations}
          >
            <option value="">-- Pilih Lokasi (Opsional) --</option>
            {locations.map((loc) => (
              <option key={loc.id} value={loc.id}>
                {loc.project_name} — {loc.city}
              </option>
            ))}
          </Select>
          <p className="mt-1 text-xs text-slate-400">
            Lokasi dapat diatur ulang dari halaman keranjang sebelum checkout booking.
          </p>
        </div>

        {/* Rate summary (read-only, from API) */}
        <div className="flex items-center justify-between rounded-xl bg-slate-50 border border-slate-100 p-3 text-sm">
          <span className="flex items-center gap-2 text-slate-600">
            <Truck size={16} className="text-primary-600" />
            Tarif Sewa
          </span>
          <span className="font-bold text-slate-900">{rateLabel}</span>
        </div>

        {errors.quantity && (
          <Alert variant="danger">Mohon periksa kembali inputan yang ditandai merah.</Alert>
        )}

        <div className="flex justify-end gap-3 pt-2 border-t border-slate-100">
          <Button type="button" variant="outline" onClick={onClose} disabled={isSubmitting}>
            Batal
          </Button>
          <Button type="submit" isLoading={isSubmitting} className="gap-2">
            <ShoppingCart size={16} />
            Tambah ke Keranjang
          </Button>
        </div>

        <button
          type="button"
          onClick={() => {
            onClose()
            navigate('/app/cart')
          }}
          className="text-xs text-primary-600 hover:text-primary-700 font-medium cursor-pointer"
        >
          Lihat Keranjang Sewa →
        </button>
      </form>
    </Modal>
  )
}
export default AddToCartModal