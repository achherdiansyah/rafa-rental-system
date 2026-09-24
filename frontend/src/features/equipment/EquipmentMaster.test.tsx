import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { AdminEquipmentMasterPage } from './pages/AdminEquipmentMasterPage'
import { equipmentService } from './services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/equipmentService', () => ({
  equipmentService: {
    getTypes: vi.fn(),
    createType: vi.fn(),
    updateType: vi.fn(),
    deleteType: vi.fn(),
    getModels: vi.fn(),
    createModel: vi.fn(),
    updateModel: vi.fn(),
    deleteModel: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <ToastProvider>
      <AdminEquipmentMasterPage />
    </ToastProvider>
  )
}

describe('Admin Equipment Master Page', () => {
  const mockTypes = [
    { id: 1, name: 'Excavator', description: 'Pengeruk tanah', models_count: 2 },
    { id: 2, name: 'Bulldozer', description: 'Perata tanah', models_count: 1 },
  ]

  const mockModels = [
    {
      id: 101,
      equipment_type_id: 1,
      brand: 'Komatsu',
      model_name: 'PC200-8',
      capacity_value: 20.0,
      capacity_unit: 'Ton',
      is_active: true,
      type: { id: 1, name: 'Excavator', description: 'Pengeruk tanah' },
      units_count: 3,
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockTypes,
      meta: { current_page: 1, per_page: 10, total: 2, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockModels,
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
  })

  it('renders page header and tabs correctly', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /master data armada/i })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: /model armada/i })).toBeInTheDocument()
    expect(screen.getByRole('tab', { name: /kategori \/ tipe alat/i })).toBeInTheDocument()

    // Waits for models to be loaded
    await waitFor(() => {
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByText('20 Ton')).toBeInTheDocument()
      expect(screen.getByText('Aktif')).toBeInTheDocument()
    })
  })

  it('switches to Tipe Alat tab and renders types list', async () => {
    renderComponent()

    const typeTab = screen.getByRole('tab', { name: /kategori \/ tipe alat/i })
    fireEvent.click(typeTab)

    await waitFor(() => {
      expect(screen.getByText('Excavator')).toBeInTheDocument()
      expect(screen.getByText('Bulldozer')).toBeInTheDocument()
      expect(screen.getByText('Pengeruk tanah')).toBeInTheDocument()
    })
  })

  it('opens Create Type modal and submits new equipment type', async () => {
    vi.mocked(equipmentService.createType).mockResolvedValue({
      id: 3,
      name: 'Wheel Loader',
      description: 'Pemindah material',
    })

    renderComponent()

    // Switch to types tab
    fireEvent.click(screen.getByRole('tab', { name: /kategori \/ tipe alat/i }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tambah tipe baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /tambah tipe baru/i }))

    // Modal is opened
    expect(screen.getByRole('heading', { name: /tambah kategori tipe baru/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/nama tipe \/ kategori/i), { target: { value: 'Wheel Loader' } })
    fireEvent.change(screen.getByLabelText(/deskripsi \/ fungsi operasional/i), { target: { value: 'Pemindah material' } })

    fireEvent.click(screen.getByRole('button', { name: /^tambah tipe$/i }))

    await waitFor(() => {
      expect(equipmentService.createType).toHaveBeenCalledWith({
        name: 'Wheel Loader',
        description: 'Pemindah material',
      })
    })
  })

  it('opens Create Model modal and submits new equipment model', async () => {
    vi.mocked(equipmentService.createModel).mockResolvedValue({
      id: 102,
      equipment_type_id: 1,
      brand: 'Caterpillar',
      model_name: 'CAT 320D',
      capacity_value: 20,
      capacity_unit: 'Ton',
      is_active: true,
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tambah model baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /tambah model baru/i }))

    expect(screen.getByRole('heading', { name: /tambah model armada baru/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/merk \/ pabrikan/i), { target: { value: 'Caterpillar' } })
    fireEvent.change(screen.getByLabelText(/nama seri model/i), { target: { value: 'CAT 320D' } })
    fireEvent.change(screen.getByLabelText(/nilai kapasitas/i), { target: { value: '20' } })

    fireEvent.click(screen.getByRole('button', { name: /^tambah model$/i }))

    await waitFor(() => {
      expect(equipmentService.createModel).toHaveBeenCalledWith(expect.objectContaining({
        brand: 'Caterpillar',
        model_name: 'CAT 320D',
        capacity_value: 20,
      }))
    })
  })
})
