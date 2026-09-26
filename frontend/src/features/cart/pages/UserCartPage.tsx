import React, { useState, useEffect } from 'react'
import { useNavigate } from 'react-router-dom'
import { ShoppingCart, Trash2, Truck, MapPin, Info, ArrowRight } from 'lucide-react'
import { cartService } from '../services/cartService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import type { Cart, CartItem } from '@/types/cart'
import type { ProjectLocation } from '@/types/projectLocation'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Select } from '@/components/form/Select'
import { Input } from '@/components/form/Input'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Alert } from '@/components/feedback/Alert'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { useToast } from '@/hooks/useToast'

const formatRupiah = (val: number) => {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    maximumFractionDigits: 0,
  }).format(val)
}

export const UserCartPage: React.FC = () => {
  const navigate = useNavigate()
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [cart, setCart] = useState<Cart | null>(null)
  const [isLoading, setIsLoading] = useState(true)
  const [apiError, setApiError] = useState<string | null>(null)

  const [locations, setLocations] = useState<ProjectLocation[]>([])
  const [isLocationLoading, setIsLocationLoading] = useState(false)

  // Qty / date editing states
  const [updatingItemId, setUpdatingItemId] = useState<number | null>(null)
  const [quantityInputs, setQuantityInputs] = useState<Record<number, number>>({})
  const [startDates, setStartDates] = useState<Record<number, string>>({})
  const [endDates, setEndDates] = useState<Record<number, string>>({})

  // Delete dialog
  const [itemToDelete, setItemToDelete] = useState<CartItem | null>(null)
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [isClearOpen, setIsClearOpen] = useState(false)
  const [isClearing, setIsClearing] = useState(false)

  const loadCart = async () => {
    setIsLoading(true)
    setApiError(null)
    try {
      const res = await cartService.getCart()
      if (res.success && res.data) {
        setCart(res.data)

        // Initialize editing states from cart
        const qty: Record<number, number> = {}
        const sd: Record<number, string> = {}
        const ed: Record<number, string> = {}
        res.data.items.forEach((it) => {
          qty[it.id] = it.quantity
          sd[it.id] = it.start_date
          ed[it.id] = it.end_date
        })
        setQuantityInputs(qty)
        setStartDates(sd)
        setEndDates(ed)
      }
    } catch (err: any) {
      setApiError(err?.message || 'Gagal memuat keranjang sewa.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadCart()
  }, [])

  useEffect(() => {
    const loadLocations = async () => {
      setIsLocationLoading(true)
      try {
        const res = await projectLocationService.getLocations(1, 100)
        if (res.success && res.data) {
          setLocations(res.data)
        }
      } catch {
        setLocations([])
      } finally {
        setIsLocationLoading(false)
      }
    }
    loadLocations()
  }, [])

  const applyCart = (next: Cart) => {
    setCart(next)
    if (next.items) {
      const qty: Record<number, number> = {}
      const sd: Record<number, string> = {}
      const ed: Record<number, string> = {}
      next.items.forEach((it) => {
        qty[it.id] = it.quantity
        sd[it.id] = it.start_date
        ed[it.id] = it.end_date
      })
      setQuantityInputs(qty)
      setStartDates(sd)
      setEndDates(ed)
    }
  }

  const handleUpdateItem = async (item: CartItem) => {
    setUpdatingItemId(item.id)
    try {
      const data = {
        quantity: quantityInputs[item.id],
        start_date: startDates[item.id],
        end_date: endDates[item.id],
      }
      const res = await cartService.updateItem(item.id, data)
      if (res.success && res.data) {
        applyCart(res.data)
        showSuccessToast('Item keranjang diperbarui.')
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memperbarui item.')
    } finally {
      setUpdatingItemId(null)
    }
  }

  const handleDeleteItem = async () => {
    if (!itemToDelete) return
    try {
      const res = await cartService.removeItem(itemToDelete.id)
      if (res.success && res.data) {
        applyCart(res.data)
        showSuccessToast('Item berhasil dihapus dari keranjang.')
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal menghapus item.')
    } finally {
      setIsDeleteOpen(false)
      setItemToDelete(null)
    }
  }

  const handleClearCart = async () => {
    setIsClearing(true)
    try {
      const res = await cartService.clearCart()
      if (res.success && res.data) {
        applyCart(res.data)
        showSuccessToast('Keranjang sewa telah dikosongkan.')
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal mengosongkan keranjang.')
    } finally {
      setIsClearing(false)
      setIsClearOpen(false)
    }
  }

  const handleLocationChange = async (locationId: string) => {
    if (!locationId) return
    try {
      const res = await cartService.updateLocation(Number(locationId))
      if (res.success && res.data) {
        applyCart(res.data)
        showSuccessToast('Lokasi proyek keranjang diperbarui.')
      }
    } catch (err: any) {
      showErrorToast(err?.message || 'Gagal memperbarui lokasi proyek.')
    }
  }

  if (isLoading) {
    return (
      <div className="space-y-5">
        <Skeleton className="h-8 w-56" />
        <Card className="p-5 space-y-4">
          <Skeleton className="h-6 w-40" />
          <Skeleton className="h-20 w-full rounded-xl" />
        </Card>
      </div>
    )
  }

  const items = cart?.items ?? []
  const isEmpty = items.length === 0

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight flex items-center gap-2">
            <ShoppingCart className="text-primary-600" size={24} />
            Keranjang Sewa
          </h2>
          <p className="text-sm text-slate-500 mt-1">
            Tinjau dan sesuaikan kebutuhan sewa armada sebelum mengajukan booking.
          </p>
        </div>
        {!isEmpty && (
          <Button
            variant="outline"
            size="sm"
            className="text-rose-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700"
            onClick={() => setIsClearOpen(true)}
          >
            <Trash2 size={14} />
            Kosongkan Keranjang
          </Button>
        )}
      </div>

      {apiError && (
        <Alert variant="danger" title="Gagal Memuat Keranjang">
          {apiError}
        </Alert>
      )}

      {isEmpty && !apiError ? (
        <EmptyState
          icon={<ShoppingCart className="w-12 h-12" />}
          title="Keranjang Sewa Masih Kosong"
          description="Temukan armada alat berat yang Anda butuhkan melalui katalog, lalu tambahkan ke keranjang untuk mengajukan sewa."
          action={
            <Button onClick={() => navigate('/app/equipment')} className="gap-2">
              <Truck size={16} />
              Jelajahi Katalog Alat
            </Button>
          }
        />
      ) : (
        <div className="space-y-5">
          {/* Project Location Selector */}
          <Card className="p-5">
            <div className="flex flex-col sm:flex-row sm:items-center gap-4">
              <div className="flex items-center gap-2 text-sm text-slate-700 shrink-0">
                <MapPin size={18} className="text-primary-600" />
                <span className="font-medium">Lokasi Proyek:</span>
              </div>
              <div className="flex-1">
                <Select
                  value={cart?.project_location_id ? String(cart.project_location_id) : ''}
                  onChange={(e) => handleLocationChange(e.target.value)}
                  disabled={isLocationLoading}
                >
                  <option value="">-- Pilih Lokasi Proyek --</option>
                  {locations.map((loc) => (
                    <option key={loc.id} value={loc.id}>
                      {loc.project_name} — {loc.city}
                    </option>
                  ))}
                </Select>
              </div>
              {!cart?.project_location_id && (
                <button
                  type="button"
                  onClick={() => navigate('/app/locations')}
                  className="text-xs text-primary-600 hover:text-primary-700 font-medium cursor-pointer shrink-0"
                >
                  + Daftarkan Lokasi Baru
                </button>
              )}
            </div>
            {!cart?.project_location_id && (
              <p className="mt-2 text-xs text-slate-400 flex items-center gap-1.5">
                <Info size={13} /> Lokasi proyek wajib dipilih sebelum lanjut ke tahap booking.
              </p>
            )}
          </Card>

          {/* Cart Items */}
          {items.map((item) => {
            const model = item.model
            const photo = model?.attachments?.find((a) => a.document_type === 'EQUIPMENT_PHOTO')
            const price = model?.prices?.find((p) => p.is_all_in === item.is_all_in)

            return (
              <Card key={item.id} className="p-5">
                <div className="flex flex-col sm:flex-row gap-4">
                  {/* Thumbnail */}
                  <div className="w-full sm:w-28 h-24 bg-slate-100 rounded-xl overflow-hidden shrink-0 flex items-center justify-center border border-slate-200">
                    {photo?.url ? (
                      <img src={photo.url} alt={model?.model_name} className="w-full h-full object-cover" loading="lazy" />
                    ) : (
                      <Truck className="text-slate-400" size={28} />
                    )}
                  </div>

                  {/* Details */}
                  <div className="flex-1 space-y-3">
                    <div className="flex items-start justify-between gap-3">
                      <div>
                        <h4 className="text-base font-bold text-slate-900">{model?.brand} {model?.model_name}</h4>
                        <div className="flex items-center gap-2 mt-1">
                          <Badge variant={item.is_all_in ? 'default' : 'secondary'} size="sm">
                            {item.is_all_in ? 'All-in (Unit + BBM + Operator)' : 'Non All-in (Bare Rental)'}
                          </Badge>
                          {price && (
                            <span className="text-xs text-slate-500">{formatRupiah(price.base_rate)} / jam</span>
                          )}
                        </div>
                      </div>
                      {price && (
                        <div className="text-right shrink-0">
                          <span className="text-xs text-slate-400 block">Tarif Satuan</span>
                          <span className="font-mono font-bold text-slate-900 text-sm">{formatRupiah(price.base_rate)}</span>
                        </div>
                      )}
                    </div>

                    {/* Editable: quantity & dates */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-100">
                      <Input
                        label="Jumlah Unit"
                        type="number"
                        min={1}
                        value={quantityInputs[item.id] ?? item.quantity}
                        onChange={(e) => setQuantityInputs({ ...quantityInputs, [item.id]: parseInt(e.target.value || '0', 10) })}
                      />
                      <Input
                        label="Mulai"
                        type="date"
                        value={startDates[item.id] ?? item.start_date}
                        onChange={(e) => setStartDates({ ...startDates, [item.id]: e.target.value })}
                      />
                      <Input
                        label="Selesai"
                        type="date"
                        value={endDates[item.id] ?? item.end_date}
                        onChange={(e) => setEndDates({ ...endDates, [item.id]: e.target.value })}
                      />
                    </div>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        className="text-rose-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700"
                        onClick={() => {
                          setItemToDelete(item)
                          setIsDeleteOpen(true)
                        }}
                      >
                        <Trash2 size={14} />
                        Hapus
                      </Button>
                      <Button
                        variant="primary"
                        size="sm"
                        isLoading={updatingItemId === item.id}
                        onClick={() => handleUpdateItem(item)}
                        disabled={updatingItemId !== null}
                      >
                        Simpan Perubahan
                      </Button>
                    </div>
                  </div>
                </div>
              </Card>
            )
          })}

          {/* Booking Preparation CTA */}
          {!isEmpty && (
            <div className="flex flex-col sm:flex-row items-center justify-between gap-3 rounded-2xl border border-primary-200 bg-primary-50/50 p-5">
              <div className="flex items-start gap-3 text-sm text-slate-600">
                <Info size={18} className="text-primary-600 shrink-0 mt-0.5" />
                <p>
                  Pastikan lokasi proyek sudah dipilih dan tanggal sewa sudah sesuai. Ketersediaan unit fisik
                  akan divalidasi dan unit ditugaskan oleh Admin pada tahap persetujuan booking.
                </p>
              </div>
              <Button
                className="gap-2 shrink-0"
                disabled={!cart?.project_location_id || items.length === 0}
                onClick={() => showSuccessToast('Checkout booking tersedia pada Phase 8D.')}
              >
                Lanjut ke Booking
                <ArrowRight size={16} />
              </Button>
            </div>
          )}
        </div>
      )}

      {/* Delete item confirmation */}
      <ConfirmDialog
        isOpen={isDeleteOpen}
        onClose={() => setIsDeleteOpen(false)}
        onConfirm={handleDeleteItem}
        title="Hapus Item dari Keranjang"
        message={`Hapus ${itemToDelete?.model?.brand ?? ''} ${itemToDelete?.model?.model_name ?? ''} dari keranjang sewa Anda?`}
        confirmText="Ya, Hapus"
        cancelText="Batal"
      />

      {/* Clear cart confirmation */}
      <ConfirmDialog
        isOpen={isClearOpen}
        onClose={() => setIsClearOpen(false)}
        onConfirm={handleClearCart}
        title="Kosongkan Keranjang"
        message="Seluruh item di keranjang sewa akan dihapus. Tindakan ini tidak dapat dibatalkan."
        confirmText="Ya, Kosongkan"
        cancelText="Batal"
        isLoading={isClearing}
      />
    </div>
  )
}
export default UserCartPage