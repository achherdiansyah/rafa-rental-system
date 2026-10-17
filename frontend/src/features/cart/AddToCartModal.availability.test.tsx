import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AddToCartModal } from './components/AddToCartModal'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import { cartService } from './services/cartService'
import { ToastProvider } from '@/app/ToastContext'
import type { EquipmentModel } from '@/types/equipment'

vi.mock('@/features/equipment/services/equipmentService', () => ({
  equipmentService: {
    getModel: vi.fn(),
    estimateMobDemob: vi.fn(),
  },
}))

vi.mock('@/features/project/services/projectLocationService', () => ({
  projectLocationService: {
    getLocations: vi.fn(),
  },
}))

vi.mock('./services/cartService', () => ({
  cartService: {
    addItem: vi.fn(),
  },
}))

const model = {
  id: 5,
  equipment_type_id: 1,
  brand: 'Komatsu',
  model_name: 'PC200-8',
  capacity_value: 20,
  capacity_unit: 'Ton',
  is_active: true,
  prices: [
    { id: 1, equipment_model_id: 5, price_type: 'HOURLY', is_all_in: false, base_rate: 250000, minimum_hours: 8, overtime_rate: 250000, mob_cost: 0, demob_cost: 0, effective_date: '2026-01-01' },
  ],
  attachments: [],
} as EquipmentModel

const renderModal = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AddToCartModal isOpen onClose={() => undefined} model={model} />
      </ToastProvider>
    </MemoryRouter>
  )

describe('AddToCartModal period availability gate', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(projectLocationService.getLocations).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ id: 3, user_id: 1, project_name: 'Proyek A', address: 'Jl. A', city: 'Bandung', pic_name: 'A', pic_phone: '08', latitude: -6.9, longitude: 107.6, is_active: true, created_at: '', updated_at: '' }],
      meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 },
    } as any)
  })

  const fillDates = () => {
    fireEvent.change(screen.getByLabelText(/tanggal mulai sewa/i), { target: { value: '2027-01-10' } })
    fireEvent.change(screen.getByLabelText(/tanggal selesai sewa/i), { target: { value: '2027-01-12' } })
  }

  it('disables submit when the selected period has fewer units than requested', async () => {
    vi.mocked(equipmentService.getModel).mockResolvedValue({
      ...model,
      available_units_count: 0,
      availability_status: 'BOOKED',
      booked_from: '2027-01-10',
    } as EquipmentModel)

    renderModal()
    fillDates()

    await waitFor(() => expect(equipmentService.getModel).toHaveBeenCalledWith(5, {
      start_date: '2027-01-10',
      end_date: '2027-01-12',
    }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tambah ke keranjang/i })).toBeDisabled()
    })
    expect(screen.getByText(/dipesan mulai/i)).toBeInTheDocument()
    expect(screen.getByText(/hanya 0 unit tersedia/i)).toBeInTheDocument()
    expect(cartService.addItem).not.toHaveBeenCalled()
  })

  it('enables submit when the period has enough units', async () => {
    vi.mocked(equipmentService.getModel).mockResolvedValue({
      ...model,
      available_units_count: 3,
      availability_status: 'AVAILABLE',
    } as EquipmentModel)

    renderModal()
    fillDates()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tambah ke keranjang/i })).toBeEnabled()
    })
    expect(screen.getByText(/tersedia 3 unit untuk periode ini/i)).toBeInTheDocument()
  })
})
