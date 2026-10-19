import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserCartPage } from './pages/UserCartPage'
import { AddToCartModal } from './components/AddToCartModal'
import { cartService } from './services/cartService'
import { projectLocationService } from '@/features/project/services/projectLocationService'
import { equipmentService } from '@/features/equipment/services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'
import type { Cart } from '@/types/cart'
import { jakartaToday, minRentalStartDate } from '@/utils/rentalDates'

const addDays = (ymd: string, days: number): string => {
  const [y, m, d] = ymd.split('-').map(Number)
  return new Date(Date.UTC(y, m - 1, d + days)).toISOString().slice(0, 10)
}

vi.mock('./services/cartService', () => ({
  cartService: {
    getCart: vi.fn(),
    addItem: vi.fn(),
    updateItem: vi.fn(),
    removeItem: vi.fn(),
    clearCart: vi.fn(),
    updateLocation: vi.fn(),
  },
}))

vi.mock('@/features/project/services/projectLocationService', () => ({
  projectLocationService: {
    getLocations: vi.fn(),
  },
}))

vi.mock('@/features/equipment/services/equipmentService', () => ({
  equipmentService: {
    getModel: vi.fn(),
    estimateMobDemob: vi.fn(),
  },
}))

const mockModel = {
  id: 5,
  equipment_type_id: 1,
  brand: 'Komatsu',
  model_name: 'PC200-8',
  capacity_value: 20,
  capacity_unit: 'Ton',
  is_active: true,
  type: { id: 1, name: 'Excavator' },
  prices: [
    { id: 1, equipment_model_id: 5, price_type: 'HOURLY', is_all_in: false, base_rate: 300000, minimum_hours: 8 },
    { id: 2, equipment_model_id: 5, price_type: 'HOURLY', is_all_in: true, base_rate: 450000, minimum_hours: 8 },
  ],
  attachments: [],
  units_count: 3,
} as any

const mockCart: Cart = {
  id: 1,
  user_id: 10,
  project_location_id: 1,
  project_location: {
    id: 1,
    user_id: 10,
    project_name: 'Flyover Cisauk',
    address: 'Jl. Lapan',
    city: 'Tangerang',
    pic_name: 'Budi',
    pic_phone: '08123',
    latitude: null,
    longitude: null,
    is_active: true,
  } as any,
  items: [
    {
      id: 100,
      cart_id: 1,
      equipment_model_id: 5,
      quantity: 2,
      is_all_in: false,
      start_date: addDays(minRentalStartDate(), 1),
      end_date: addDays(minRentalStartDate(), 8),
      model: mockModel,
    } as any,
  ],
  created_at: '',
  updated_at: '',
}

const mockLocations = [mockCart.project_location] as any[]

describe('Cart UI Suite', () => {
  beforeEach(() => {
    vi.clearAllMocks()
      vi.mocked(projectLocationService.getLocations).mockResolvedValue({
        success: true,
        message: 'OK',
        data: mockLocations,
        meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 },
      } as any)
      vi.mocked(equipmentService.getModel).mockResolvedValue({
        ...mockModel,
        available_units_count: 3,
        availability_status: 'AVAILABLE',
      } as any)
    })

  it('shows empty state when cart is empty', async () => {
    vi.mocked(cartService.getCart).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockCart, items: [], project_location_id: null, project_location: null },
    } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <UserCartPage />
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByText(/keranjang sewa masih kosong/i)).toBeInTheDocument()
      expect(screen.getByRole('button', { name: /jelajahi katalog alat/i })).toBeInTheDocument()
    })
  })

  it('lists cart items with equipment, quantity, and scheme badge', async () => {
    vi.mocked(cartService.getCart).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <UserCartPage />
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
      expect(screen.getByText(/non all-in \(bare rental\)/i)).toBeInTheDocument()
    })
  })

  it('shows required location modal if user has no project locations', async () => {
    vi.mocked(projectLocationService.getLocations).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
    } as any)

    const onClose = vi.fn()
    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen={true} onClose={onClose} model={mockModel} />
        </ToastProvider>
      </MemoryRouter>
    )

    expect(await screen.findByText(/lokasi proyek diperlukan/i)).toBeInTheDocument()
    expect(screen.getByText(/silakan tambahkan lokasi proyek terlebih dahulu sebelum melanjutkan/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /tambah lokasi proyek/i })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /batalkan/i })).toBeInTheDocument()
  })

  it('updates item quantity and calls service on save', async () => {
    vi.mocked(cartService.getCart).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)
    vi.mocked(cartService.updateItem).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <UserCartPage />
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByLabelText('Jumlah Unit')).toBeInTheDocument()
    })

    const qtyInput = screen.getByLabelText('Jumlah Unit')
    fireEvent.change(qtyInput, { target: { value: '5' } })

    const saveBtn = screen.getByRole('button', { name: /simpan perubahan/i })
    fireEvent.click(saveBtn)

    await waitFor(() => {
      expect(cartService.updateItem).toHaveBeenCalledWith(
        100,
        expect.objectContaining({ quantity: 5 })
      )
    })
  })

  it('confirms before deleting an item', async () => {
    vi.mocked(cartService.getCart).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)
    vi.mocked(cartService.removeItem).mockResolvedValue({
      success: true,
      message: 'OK',
      data: { ...mockCart, items: [] },
    } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <UserCartPage />
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByText('Komatsu PC200-8')).toBeInTheDocument()
    })

    const deleteBtn = screen.getByRole('button', { name: /hapus/i })
    fireEvent.click(deleteBtn)

    expect(screen.getByText(/hapus item dari keranjang/i)).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /ya, hapus/i }))

    await waitFor(() => {
      expect(cartService.removeItem).toHaveBeenCalledWith(100)
    })
  })

  it('handles API error when loading cart', async () => {
    vi.mocked(cartService.getCart).mockRejectedValueOnce({
      message: 'Koneksi server terputus.',
    })

    render(
      <MemoryRouter>
        <ToastProvider>
          <UserCartPage />
        </ToastProvider>
      </MemoryRouter>
    )

    await waitFor(() => {
      expect(screen.getByText(/gagal memuat keranjang/i)).toBeInTheDocument()
      expect(screen.getByText('Koneksi server terputus.')).toBeInTheDocument()
    })
  })

  it('add to cart modal validates dates before submit', async () => {
    vi.mocked(cartService.addItem).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen model={mockModel} onClose={vi.fn()} />
        </ToastProvider>
      </MemoryRouter>
    )

    expect(screen.getByRole('heading', { name: /sewa komatsu pc200-8/i })).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: /tambah ke keranjang/i }))

    await waitFor(() => {
      expect(screen.getByText(/tanggal mulai wajib diisi/i)).toBeInTheDocument()
      expect(screen.getByText(/tanggal selesai wajib diisi/i)).toBeInTheDocument()
    })

    expect(cartService.addItem).not.toHaveBeenCalled()
  })

  it('rejects today as the rental start date (H-1 rule)', async () => {
    vi.mocked(cartService.addItem).mockResolvedValue({ success: true, message: 'OK', data: mockCart } as any)

    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen model={mockModel} onClose={vi.fn()} />
        </ToastProvider>
      </MemoryRouter>
    )

    const startInput = screen.getByLabelText(/tanggal mulai sewa/i) as HTMLInputElement
    fireEvent.change(startInput, { target: { value: jakartaToday() } })
    fireEvent.change(screen.getByLabelText(/tanggal selesai sewa/i), { target: { value: addDays(jakartaToday(), 3) } })
    expect(startInput.value).toBe(jakartaToday())
    expect(startInput.min).toBe(minRentalStartDate())

    const submit = screen.getByRole('button', { name: /tambah ke keranjang/i })
    await waitFor(() => expect(submit).toBeEnabled())
    fireEvent.click(submit)

    await waitFor(() => expect(startInput.getAttribute('aria-invalid')).toBe('true'))
    expect(screen.getByText(/pemesanan wajib dilakukan H-1/i)).toBeInTheDocument()
    expect(cartService.addItem).not.toHaveBeenCalled()
  })

  it('clears an invalid end date when the start date moves later', async () => {
    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen model={mockModel} onClose={vi.fn()} />
        </ToastProvider>
      </MemoryRouter>
    )

    const startInput = screen.getByLabelText(/tanggal mulai sewa/i) as HTMLInputElement
    const endInput = screen.getByLabelText(/tanggal selesai sewa/i) as HTMLInputElement

    fireEvent.change(startInput, { target: { value: minRentalStartDate() } })
    fireEvent.change(endInput, { target: { value: addDays(minRentalStartDate(), 3) } })
    expect(endInput.value).toBe(addDays(minRentalStartDate(), 3))

    fireEvent.change(startInput, { target: { value: addDays(minRentalStartDate(), 10) } })

    expect(endInput.value).toBe('')
    expect(await screen.findByText(/tidak boleh mendahului tanggal mulai/i)).toBeInTheDocument()
  })

  it('start date input exposes tomorrow as the minimum selectable date', async () => {
    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen model={mockModel} onClose={vi.fn()} />
        </ToastProvider>
      </MemoryRouter>
    )

    const startInput = screen.getByLabelText(/tanggal mulai sewa/i) as HTMLInputElement
    expect(startInput.min).toBe(minRentalStartDate())
  })

  it('add to cart modal submits successful payload', async () => {
    vi.mocked(cartService.addItem).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockCart,
    } as any)

    const onClose = vi.fn()

    render(
      <MemoryRouter>
        <ToastProvider>
          <AddToCartModal isOpen model={mockModel} onClose={onClose} />
        </ToastProvider>
      </MemoryRouter>
    )

    // Fill dates (H-1 compliant, so client-side validation accepts them)
    const start = minRentalStartDate()
    const end = addDays(start, 5)

    fireEvent.change(screen.getByLabelText(/tanggal mulai sewa/i), { target: { value: start } })
    fireEvent.change(screen.getByLabelText(/tanggal selesai sewa/i), { target: { value: end } })

    await waitFor(() => {
      expect(screen.getByText(/tersedia 3 unit untuk periode ini/i)).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /tambah ke keranjang/i }))

    await waitFor(() => {
      expect(cartService.addItem).toHaveBeenCalledWith(
        expect.objectContaining({
          equipment_model_id: 5,
          is_all_in: false,
          start_date: start,
          end_date: end,
        })
      )
      expect(onClose).toHaveBeenCalled()
    })
  })
})
