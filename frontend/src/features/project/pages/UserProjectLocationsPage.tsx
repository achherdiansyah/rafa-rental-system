import React, { useState, useEffect } from 'react'
import { Plus, Search, MapPin, Edit2, Trash2, Map } from 'lucide-react'
import { projectLocationService } from '../services/projectLocationService'
import type { ProjectLocation, CreateProjectLocationInput } from '@/types/projectLocation'
import { Card } from '@/components/ui/Card'
import { Button } from '@/components/ui/Button'
import { Badge } from '@/components/ui/Badge'
import { Input } from '@/components/form/Input'
import { Textarea } from '@/components/form/Textarea'
import { Modal } from '@/components/ui/Modal'
import { ConfirmDialog } from '@/components/ui/ConfirmDialog'
import { Skeleton } from '@/components/ui/Skeleton'
import { EmptyState } from '@/components/feedback/EmptyState'
import { Pagination } from '@/components/data-display/Pagination'
import { useToast } from '@/hooks/useToast'
import { useDebounce } from '@/hooks/useDebounce'

export const UserProjectLocationsPage: React.FC = () => {
  const { success: showSuccessToast, error: showErrorToast } = useToast()

  const [locations, setLocations] = useState<ProjectLocation[]>([])
  const [isLoading, setIsLoading] = useState(true)
  const [searchTerm, setSearchTerm] = useState('')
  const debouncedSearch = useDebounce(searchTerm, 300)

  // Pagination
  const [currentPage, setCurrentPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)

  // Modals
  const [isModalOpen, setIsModalOpen] = useState(false)
  const [editingLocation, setEditingLocation] = useState<ProjectLocation | null>(null)
  
  // Delete Dialog
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false)
  const [locationToDelete, setLocationToDelete] = useState<ProjectLocation | null>(null)

  // Form State
  const [formData, setFormData] = useState<CreateProjectLocationInput>({
    project_name: '',
    address: '',
    city: '',
    pic_name: '',
    pic_phone: '',
  })
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [isSubmitting, setIsSubmitting] = useState(false)

  const loadLocations = async (page = currentPage, search = debouncedSearch) => {
    setIsLoading(true)
    try {
      const res = await projectLocationService.getLocations(page, 9, search)
      if (res.success && res.data) {
        setLocations(res.data)
        if (res.meta) {
          setTotalPages(res.meta.last_page)
          setCurrentPage(res.meta.current_page)
        }
      }
    } catch {
      showErrorToast('Gagal memuat daftar lokasi proyek.')
    } finally {
      setIsLoading(false)
    }
  }

  useEffect(() => {
    loadLocations(1, debouncedSearch)
  }, [debouncedSearch])

  useEffect(() => {
    loadLocations(currentPage, debouncedSearch)
  }, [currentPage])

  const validateForm = (): boolean => {
    const newErrors: Record<string, string> = {}
    if (!formData.project_name.trim()) newErrors.project_name = 'Nama proyek wajib diisi.'
    if (!formData.address.trim()) newErrors.address = 'Alamat proyek wajib diisi.'
    if (!formData.city.trim()) newErrors.city = 'Kota wajib diisi.'
    if (!formData.pic_name.trim()) newErrors.pic_name = 'Nama PIC wajib diisi.'
    if (!formData.pic_phone.trim()) newErrors.pic_phone = 'Nomor telepon PIC wajib diisi.'
    
    setErrors(newErrors)
    return Object.keys(newErrors).length === 0
  }

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    if (!validateForm()) return

    setIsSubmitting(true)
    try {
      if (editingLocation) {
        await projectLocationService.updateLocation(editingLocation.id, formData)
        showSuccessToast('Lokasi proyek berhasil diperbarui.')
      } else {
        await projectLocationService.createLocation(formData)
        showSuccessToast('Lokasi proyek berhasil didaftarkan.')
      }
      setIsModalOpen(false)
      loadLocations()
    } catch (err: any) {
      if (err?.response?.data?.errors) {
        setErrors(err.response.data.errors)
      } else {
        showErrorToast('Terjadi kesalahan saat menyimpan lokasi proyek.')
      }
    } finally {
      setIsSubmitting(false)
    }
  }

  const openCreateModal = () => {
    setEditingLocation(null)
    setFormData({
      project_name: '',
      address: '',
      city: '',
      pic_name: '',
      pic_phone: '',
    })
    setErrors({})
    setIsModalOpen(true)
  }

  const openEditModal = (loc: ProjectLocation) => {
    setEditingLocation(loc)
    setFormData({
      project_name: loc.project_name,
      address: loc.address,
      city: loc.city,
      pic_name: loc.pic_name,
      pic_phone: loc.pic_phone,
    })
    setErrors({})
    setIsModalOpen(true)
  }

  const handleDelete = async () => {
    if (!locationToDelete) return

    try {
      await projectLocationService.deleteLocation(locationToDelete.id)
      showSuccessToast('Lokasi proyek berhasil dihapus.')
      loadLocations()
    } catch (err: any) {
      if (err?.response?.status === 409) {
        showErrorToast('Tidak dapat menghapus lokasi proyek yang sedang memiliki pesanan sewa aktif.')
      } else {
        showErrorToast('Terjadi kesalahan saat menghapus lokasi proyek.')
      }
    } finally {
      setIsDeleteDialogOpen(false)
      setLocationToDelete(null)
    }
  }

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-slate-900 tracking-tight">Lokasi Proyek</h2>
          <p className="text-sm text-slate-500">Kelola daftar lokasi pengerjaan proyek untuk pengiriman armada.</p>
        </div>
        <Button onClick={openCreateModal} className="gap-2 shrink-0">
          <Plus size={16} />
          Tambah Lokasi Baru
        </Button>
      </div>

      {/* Toolbar */}
      <div className="flex flex-col sm:flex-row gap-3">
        <div className="relative flex-1 max-w-md">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" size={16} />
          <Input
            placeholder="Cari nama proyek, kota, atau PIC..."
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            className="pl-9"
          />
        </div>
      </div>

      {/* Content Grid */}
      {isLoading ? (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
          {[1, 2, 3].map((i) => (
            <Card key={i} className="p-5 space-y-4">
              <Skeleton className="h-6 w-3/4" />
              <div className="space-y-2">
                <Skeleton className="h-4 w-full" />
                <Skeleton className="h-4 w-5/6" />
              </div>
            </Card>
          ))}
        </div>
      ) : locations.length > 0 ? (
        <div className="space-y-6">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
            {locations.map((loc) => (
              <Card key={loc.id} className="p-5 flex flex-col hover:border-primary-300 transition-colors">
                <div className="flex justify-between items-start mb-3">
                  <div className="flex-1 pr-4">
                    <h3 className="font-bold text-slate-900 leading-tight mb-1">{loc.project_name}</h3>
                    <div className="flex items-center gap-1.5 text-xs text-slate-500">
                      <MapPin size={12} className="shrink-0" />
                      {loc.city}
                    </div>
                  </div>
                  {!loc.is_active && (
                    <Badge variant="secondary" size="sm">Nonaktif</Badge>
                  )}
                </div>

                <div className="text-sm text-slate-600 mb-4 flex-1">
                  <p className="line-clamp-2">{loc.address}</p>
                </div>

                <div className="bg-slate-50 rounded-lg p-3 text-xs space-y-1.5 border border-slate-100 mb-4">
                  <div className="flex justify-between">
                    <span className="text-slate-500">PIC Lapangan:</span>
                    <span className="font-medium text-slate-900">{loc.pic_name}</span>
                  </div>
                  <div className="flex justify-between">
                    <span className="text-slate-500">No. Telepon:</span>
                    <span className="font-medium text-slate-900">{loc.pic_phone}</span>
                  </div>
                </div>

                <div className="flex justify-end gap-2 mt-auto pt-2 border-t border-slate-100">
                  <Button variant="outline" size="sm" onClick={() => openEditModal(loc)} className="gap-1.5">
                    <Edit2 size={14} />
                    Edit
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    className="text-rose-600 hover:bg-rose-50 hover:border-rose-200 hover:text-rose-700"
                    onClick={() => {
                      setLocationToDelete(loc)
                      setIsDeleteDialogOpen(true)
                    }}
                  >
                    <Trash2 size={14} />
                  </Button>
                </div>
              </Card>
            ))}
          </div>
          
          {totalPages > 1 && (
            <Pagination
              currentPage={currentPage}
              totalPages={totalPages}
              onPageChange={setCurrentPage}
            />
          )}
        </div>
      ) : (
        <EmptyState
          icon={<Map className="w-12 h-12" />}
          title="Belum Ada Lokasi Proyek"
          description="Anda belum mendaftarkan lokasi proyek. Tambahkan lokasi proyek Anda agar dapat melakukan pemesanan sewa armada."
          action={
            <Button onClick={openCreateModal} className="gap-2">
              <Plus size={16} />
              Daftarkan Lokasi Proyek
            </Button>
          }
        />
      )}

      {/* Form Modal */}
      <Modal
        isOpen={isModalOpen}
        onClose={() => !isSubmitting && setIsModalOpen(false)}
        title={editingLocation ? 'Edit Lokasi Proyek' : 'Daftarkan Lokasi Proyek Baru'}
        size="md"
      >
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Nama Proyek / Area *"
            placeholder="Contoh: Proyek Tol Cisauk Raya"
            value={formData.project_name}
            onChange={(e) => setFormData({ ...formData, project_name: e.target.value })}
            error={errors.project_name}
            disabled={isSubmitting}
          />
          
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <Input
              label="Nama PIC Lapangan *"
              placeholder="Contoh: Budi Santoso"
              value={formData.pic_name}
              onChange={(e) => setFormData({ ...formData, pic_name: e.target.value })}
              error={errors.pic_name}
              disabled={isSubmitting}
            />
            <Input
              label="Nomor Telepon PIC *"
              placeholder="Contoh: 08123456789"
              value={formData.pic_phone}
              onChange={(e) => setFormData({ ...formData, pic_phone: e.target.value })}
              error={errors.pic_phone}
              disabled={isSubmitting}
            />
          </div>

          <Input
            label="Kota / Kabupaten *"
            placeholder="Contoh: Tangerang Selatan"
            value={formData.city}
            onChange={(e) => setFormData({ ...formData, city: e.target.value })}
            error={errors.city}
            disabled={isSubmitting}
          />

          <Textarea
            label="Alamat Lengkap Proyek *"
            placeholder="Contoh: Jl. Lapan Raya No. 45, Patokan dekat gerbang selatan"
            value={formData.address}
            onChange={(e) => setFormData({ ...formData, address: e.target.value })}
            error={errors.address}
            disabled={isSubmitting}
            rows={3}
          />

          <div className="flex justify-end gap-3 pt-4 border-t border-slate-100">
            <Button
              type="button"
              variant="outline"
              onClick={() => setIsModalOpen(false)}
              disabled={isSubmitting}
            >
              Batal
            </Button>
            <Button type="submit" isLoading={isSubmitting}>
              {editingLocation ? 'Simpan Perubahan' : 'Daftarkan Lokasi'}
            </Button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation */}
      <ConfirmDialog
        isOpen={isDeleteDialogOpen}
        onClose={() => !isSubmitting && setIsDeleteDialogOpen(false)}
        onConfirm={handleDelete}
        title="Hapus Lokasi Proyek"
        message={`Apakah Anda yakin ingin menghapus lokasi proyek "${locationToDelete?.project_name}"? Lokasi ini tidak akan bisa digunakan lagi untuk pemesanan baru.`}
        confirmText="Ya, Hapus Lokasi"
        cancelText="Batal"
        isLoading={isSubmitting}
      />
    </div>
  )
}
export default UserProjectLocationsPage
