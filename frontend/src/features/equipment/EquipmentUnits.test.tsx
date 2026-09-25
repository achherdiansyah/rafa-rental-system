import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { AdminEquipmentUnitsPage } from './pages/AdminEquipmentUnitsPage'
import { equipmentService } from './services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/equipmentService', () => ({
  equipmentService: {
    getTypes: vi.fn(),
    getModels: vi.fn(),
    getUnits: vi.fn(),
    createUnit: vi.fn(),
    updateUnit: vi.fn(),
    updateUnitStatus: vi.fn(),
    deleteUnit: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <ToastProvider>
      <AdminEquipmentUnitsPage />
    </ToastProvider>
  )
}

describe('Admin Equipment Physical Units Page', () => {
  const mockModels = [
    {
      id: 1,
      equipment_type_id: 1,
      brand: 'Komatsu',
      model_name: 'PC200-8',
      capacity_value: 20,
      capacity_unit: 'Ton',
      is_active: true,
      type: { id: 1, name: 'Excavator' },
    },
  ]

  const mockUnits = [
    {
      id: 10,
      equipment_model_id: 1,
      serial_number: 'KM-PC200-001',
      plate_number: 'B 9101 RFA',
      status: 'AVAILABLE' as const,
      last_hour_meter: 1250.5,
      year_of_make: 2021,
      model: mockModels[0],
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockModels,
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getUnits).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockUnits,
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
  })

  it('renders page header and physical units table', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /inventaris unit fisik/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('KM-PC200-001')).toBeInTheDocument()
      expect(screen.getByText('B 9101 RFA')).toBeInTheDocument()
      expect(screen.getByText('AVAILABLE')).toBeInTheDocument()
      expect(screen.getByText(/1.250,50 Jam/)).toBeInTheDocument()
    })
  })

  it('opens register unit modal and submits new physical unit', async () => {
    vi.mocked(equipmentService.createUnit).mockResolvedValue({
      id: 11,
      equipment_model_id: 1,
      serial_number: 'KM-PC200-002',
      plate_number: 'B 9102 RFA',
      status: 'AVAILABLE',
      last_hour_meter: 500,
      year_of_make: 2022,
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /daftarkan unit baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /daftarkan unit baru/i }))

    expect(screen.getByRole('heading', { name: /daftarkan unit fisik baru/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/nomor seri mesin \/ rangka/i), { target: { value: 'KM-PC200-002' } })
    fireEvent.change(screen.getByLabelText(/nomor plat \/ no lambung/i), { target: { value: 'B 9102 RFA' } })
    fireEvent.change(screen.getByLabelText(/hour meter awal/i), { target: { value: '500' } })

    fireEvent.click(screen.getByRole('button', { name: /^daftarkan unit$/i }))

    await waitFor(() => {
      expect(equipmentService.createUnit).toHaveBeenCalledWith(expect.objectContaining({
        serial_number: 'KM-PC200-002',
        plate_number: 'B 9102 RFA',
        last_hour_meter: 500,
      }))
    })
  })

  it('opens change status modal and submits maintenance transition', async () => {
    vi.mocked(equipmentService.updateUnitStatus).mockResolvedValue({
      id: 10,
      equipment_model_id: 1,
      serial_number: 'KM-PC200-001',
      plate_number: 'B 9101 RFA',
      status: 'MAINTENANCE',
      last_hour_meter: 1250.5,
      year_of_make: 2021,
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByLabelText(/ubah status km-pc200-001/i)).toBeInTheDocument()
    })

    fireEvent.click(screen.getByLabelText(/ubah status km-pc200-001/i))

    expect(screen.getByRole('heading', { name: /ubah status: km-pc200-001/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/catatan pemeliharaan \/ alasan/i), {
      target: { value: 'Ganti oli dan filter hidrolik' },
    })

    fireEvent.click(screen.getByRole('button', { name: /perbarui status/i }))

    await waitFor(() => {
      expect(equipmentService.updateUnitStatus).toHaveBeenCalledWith(10, {
        status: 'MAINTENANCE',
        notes: 'Ganti oli dan filter hidrolik',
      })
    })
  })

  it('opens delete confirmation dialog and confirms deletion', async () => {
    vi.mocked(equipmentService.deleteUnit).mockResolvedValue()

    renderComponent()

    await waitFor(() => {
      expect(screen.getByLabelText(/hapus km-pc200-001/i)).toBeInTheDocument()
    })

    fireEvent.click(screen.getByLabelText(/hapus km-pc200-001/i))

    expect(screen.getByRole('heading', { name: /konfirmasi hapus unit fisik/i })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /hapus unit/i }))

    await waitFor(() => {
      expect(equipmentService.deleteUnit).toHaveBeenCalledWith(10)
    })
  })
})
