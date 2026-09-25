import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { OwnerPricingPage } from './pages/OwnerPricingPage'
import { equipmentService } from './services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/equipmentService', () => ({
  equipmentService: {
    getModels: vi.fn(),
    getPrices: vi.fn(),
    createPrice: vi.fn(),
    updatePrice: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <ToastProvider>
      <OwnerPricingPage />
    </ToastProvider>
  )
}

describe('Owner Equipment Pricing Page', () => {
  const mockModels = [
    { id: 1, equipment_type_id: 1, brand: 'Komatsu', model_name: 'PC200-8', capacity_value: 20, capacity_unit: 'Ton', is_active: true },
  ]

  const mockPrices = [
    {
      id: 50,
      equipment_model_id: 1,
      price_type: 'HOURLY' as const,
      is_all_in: true,
      base_rate: 350000,
      minimum_hours: 8,
      overtime_rate: 400000,
      effective_date: '2026-10-01',
      model: mockModels[0],
      versions: [
        {
          id: 1,
          equipment_price_id: 50,
          old_base_rate: 300000,
          new_base_rate: 350000,
          changed_at: '2026-09-24T00:00:00Z',
          changed_by: 1,
          changed_by_user: { id: 1, name: 'Owner Direksi' },
        },
      ],
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

    vi.mocked(equipmentService.getPrices).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockPrices,
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
  })

  it('renders page header and master pricing table', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /master tarif & versi harga/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getAllByText(/Komatsu PC200-8/i).length).toBeGreaterThan(0)
      expect(screen.getByText('All-in')).toBeInTheDocument()
      expect(screen.getByText(/350\.000/)).toBeInTheDocument()
      expect(screen.getByText('8 Jam/hari')).toBeInTheDocument()
    })
  })

  it('opens set new price modal and submits pricing data', async () => {
    vi.mocked(equipmentService.createPrice).mockResolvedValue({
      id: 51,
      equipment_model_id: 1,
      price_type: 'HOURLY',
      is_all_in: false,
      base_rate: 225000,
      minimum_hours: 8,
      overtime_rate: 275000,
      effective_date: '2026-10-15',
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tetapkan tarif baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /tetapkan tarif baru/i }))

    expect(screen.getByRole('heading', { name: /tetapkan tarif master baru/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/tarif dasar \/ jam/i), { target: { value: '225000' } })
    fireEvent.change(screen.getByLabelText(/tarif overtime \/ jam/i), { target: { value: '275000' } })

    fireEvent.click(screen.getByRole('button', { name: /^tetapkan tarif$/i }))

    await waitFor(() => {
      expect(equipmentService.createPrice).toHaveBeenCalledWith(expect.objectContaining({
        base_rate: 225000,
        overtime_rate: 275000,
        minimum_hours: 8,
      }))
    })
  })

  it('opens version history modal and renders previous audit changes', async () => {
    renderComponent()

    await waitFor(() => {
      expect(screen.getByLabelText(/riwayat versi komatsu pc200-8/i)).toBeInTheDocument()
    })

    fireEvent.click(screen.getByLabelText(/riwayat versi komatsu pc200-8/i))

    expect(screen.getByRole('heading', { name: /riwayat versi harga: komatsu pc200-8/i })).toBeInTheDocument()

    // Assert version rates are visible
    expect(screen.getByText(/300\.000/)).toBeInTheDocument()
    expect(screen.getAllByText(/350\.000/).length).toBeGreaterThanOrEqual(1)
    expect(screen.getByText('Owner Direksi')).toBeInTheDocument()
  })
})
