import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { EquipmentCatalogPage } from './pages/EquipmentCatalogPage'
import { EquipmentDetailPage } from './pages/EquipmentDetailPage'
import { equipmentService } from './services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/equipmentService', () => ({
  equipmentService: {
    getTypes: vi.fn(),
    getModels: vi.fn(),
    getModel: vi.fn(),
  },
}))

describe('User Equipment Catalog UI', () => {
  const mockTypes = [
    { id: 1, name: 'Excavator', description: 'Pengeruk' },
  ]

  const mockModels = [
    {
      id: 10,
      equipment_type_id: 1,
      brand: 'Komatsu',
      model_name: 'PC200-8',
      capacity_value: 20,
      capacity_unit: 'Ton',
      is_active: true,
      type: mockTypes[0],
      prices: [
        { id: 1, equipment_model_id: 10, price_type: 'HOURLY', is_all_in: false, base_rate: 225000, minimum_hours: 8, overtime_rate: 275000, effective_date: '2026-01-01' },
        { id: 2, equipment_model_id: 10, price_type: 'HOURLY', is_all_in: true, base_rate: 350000, minimum_hours: 8, overtime_rate: 400000, effective_date: '2026-01-01' },
      ],
      attachments: [
        { id: 1, document_type: 'EQUIPMENT_PHOTO', file_name: 'photo.jpg', mime_type: 'image/jpeg', file_size: 100, url: 'http://localhost/photo.jpg', uploaded_by: 1 },
      ],
      units_count: 5,
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockTypes,
    } as any)

    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockModels,
      meta: { current_page: 1, per_page: 9, total: 1, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getModel).mockResolvedValue(mockModels[0] as any)
  })

  it('renders user equipment catalog grid with cards and lowest price', async () => {
    render(
      <MemoryRouter>
        <ToastProvider>
          <EquipmentCatalogPage />
        </ToastProvider>
      </MemoryRouter>
    )

    expect(screen.getByRole('heading', { name: /katalog armada alat berat/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByText('20 Ton')).toBeInTheDocument()
      expect(screen.getByText(/Rp 225\.000\/jam/)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /lihat detail/i })).toBeInTheDocument()
    })
  })

  it('filters catalog by search input', async () => {
    render(
      <MemoryRouter>
        <ToastProvider>
          <EquipmentCatalogPage />
        </ToastProvider>
      </MemoryRouter>
    )

    const searchInput = screen.getByPlaceholderText(/cari jenis alat atau merk/i)
    fireEvent.change(searchInput, { target: { value: 'Komatsu' } })

    await waitFor(() => {
      expect(equipmentService.getModels).toHaveBeenCalledWith(expect.objectContaining({
        search: 'Komatsu',
      }))
    })
  })

  it('renders equipment detail page with specifications and pricing schemes', async () => {
    render(
      <MemoryRouter initialEntries={['/app/equipment/10']}>
        <ToastProvider>
          <Routes>
            <Route path="/app/equipment/:id" element={<EquipmentDetailPage />} />
          </Routes>
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /komatsu pc200-8/i })).toBeInTheDocument()
      expect(screen.getByText('Non All-in (Unit Saja)')).toBeInTheDocument()
      expect(screen.getByText(/Rp 225\.000/)).toBeInTheDocument()
      expect(screen.getByText('All-in (Unit + BBM + Operator)')).toBeInTheDocument()
      expect(screen.getByText(/Rp 350\.000/)).toBeInTheDocument()
    })
  })
})
