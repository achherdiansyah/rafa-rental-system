import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { AdminEquipmentMasterPage } from './pages/AdminEquipmentMasterPage'
import { AdminEquipmentUnitsPage } from './pages/AdminEquipmentUnitsPage'
import { OwnerPricingPage } from './pages/OwnerPricingPage'
import { AdminBankAccountsPage } from '../bank/pages/AdminBankAccountsPage'
import { equipmentService } from './services/equipmentService'
import { bankService } from '../bank/services/bankService'
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

vi.mock('../bank/services/bankService', () => ({
  bankService: {
    getAccounts: vi.fn(),
    createAccount: vi.fn(),
    updateAccount: vi.fn(),
  },
}))

describe('Admin Master Data UI Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks()

    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [{ id: 1, name: 'Excavator', description: 'Pengeruk tanah' }],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [
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
      ],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getUnits).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)

    vi.mocked(equipmentService.getPrices).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 },
    } as any)

    vi.mocked(bankService.getAccounts).mockResolvedValue([])
  })

  it('renders equipment master page with search filter and table', async () => {
    render(
      <ToastProvider>
        <AdminEquipmentMasterPage />
      </ToastProvider>
    )

    expect(screen.getByRole('heading', { name: /master data armada/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByPlaceholderText(/cari model atau merk/i)).toBeInTheDocument()
    })
  })

  it('renders physical units empty state when no units registered', async () => {
    render(
      <ToastProvider>
        <AdminEquipmentUnitsPage />
      </ToastProvider>
    )

    expect(screen.getByRole('heading', { name: /inventaris unit fisik/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText(/tidak ada unit fisik/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /daftarkan unit pertama/i })).toBeInTheDocument()
    })
  })

  it('renders owner pricing empty state and filter options', async () => {
    render(
      <ToastProvider>
        <OwnerPricingPage />
      </ToastProvider>
    )

    expect(screen.getByRole('heading', { name: /master tarif & versi harga/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText(/belum ada master tarif/i)).toBeInTheDocument()
    })
  })

  it('renders bank accounts empty state and create action', async () => {
    render(
      <ToastProvider>
        <AdminBankAccountsPage />
      </ToastProvider>
    )

    expect(screen.getByRole('heading', { name: /rekening bank perusahaan/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText(/belum ada rekening bank/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /daftarkan rekening sekarang/i })).toBeInTheDocument()
    })
  })
})
