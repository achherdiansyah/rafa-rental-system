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
    getUnits: vi.fn(),
    createUnit: vi.fn(),
    updateUnit: vi.fn(),
    updateUnitStatus: vi.fn(),
    deleteUnit: vi.fn(),
    getPrices: vi.fn(),
    createPrice: vi.fn(),
    updatePrice: vi.fn(),
  },
}))

describe('Master Data & Pricing Frontend Integration Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('renders loading states and handles empty list gracefully', async () => {
    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)
    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)

    render(
      <ToastProvider>
        <AdminEquipmentMasterPage />
      </ToastProvider>
    )

    await waitFor(() => {
      expect(screen.getByText(/belum ada model armada/i)).toBeInTheDocument()
    })
  })

  it('handles modal form interaction for creating equipment model', async () => {
    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ id: 1, name: 'Excavator' }],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)
    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)

    render(
      <ToastProvider>
        <AdminEquipmentMasterPage />
      </ToastProvider>
    )

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /tambah model baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /tambah model baru/i }))

    await waitFor(() => {
      expect(screen.getByText(/tambah model armada baru/i)).toBeInTheDocument()
    })

    // Modal form fields present
    expect(screen.getByPlaceholderText(/komatsu/i)).toBeInTheDocument()
    expect(screen.getByPlaceholderText(/pc200-8/i)).toBeInTheDocument()
  })
})
