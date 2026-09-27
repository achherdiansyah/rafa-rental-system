import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserProjectLocationsPage } from './pages/UserProjectLocationsPage'
import { projectLocationService } from './services/projectLocationService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/projectLocationService', () => ({
  projectLocationService: {
    getLocations: vi.fn(),
    getLocation: vi.fn(),
    createLocation: vi.fn(),
    updateLocation: vi.fn(),
    deleteLocation: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <MemoryRouter>
      <ToastProvider>
        <UserProjectLocationsPage />
      </ToastProvider>
    </MemoryRouter>
  )
}

describe('Project Locations UI Suite', () => {
  const mockLocations = [
    {
      id: 1,
      user_id: 10,
      project_name: 'Pembangunan Flyover Cisauk',
      address: 'Jl. Raya Cisauk Lapan No. 45',
      city: 'Tangerang',
      pic_name: 'Bambang Sutrisno',
      pic_phone: '081234567890',
      latitude: -6.32145678,
      longitude: 106.654321,
      is_active: true,
      created_at: '2026-09-25T10:00:00Z',
      updated_at: '2026-09-25T10:00:00Z',
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(projectLocationService.getLocations).mockResolvedValue({
      success: true,
      message: 'OK',
      data: mockLocations,
      meta: { current_page: 1, per_page: 9, total: 1, last_page: 1 },
    } as any)
  })

  it('renders project locations list and card content', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /lokasi proyek/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('Pembangunan Flyover Cisauk')).toBeInTheDocument()
      expect(screen.getByText('Tangerang')).toBeInTheDocument()
      expect(screen.getByText('Bambang Sutrisno')).toBeInTheDocument()
      expect(screen.getByText('081234567890')).toBeInTheDocument()
    })
  })

  it('validates required fields when creating new location', async () => {
    renderComponent()

    const addBtn = await screen.findByRole('button', { name: /tambah lokasi baru/i })
    fireEvent.click(addBtn)

    expect(screen.getByRole('heading', { name: /daftarkan lokasi proyek baru/i })).toBeInTheDocument()

    const submitBtn = screen.getByRole('button', { name: /daftarkan lokasi/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(screen.getByText(/nama proyek wajib diisi/i)).toBeInTheDocument()
      expect(screen.getByText(/alamat proyek wajib diisi/i)).toBeInTheDocument()
      expect(screen.getByText(/kota wajib diisi/i)).toBeInTheDocument()
    })
  })

  it('submits create location form successfully', async () => {
    vi.mocked(projectLocationService.createLocation).mockResolvedValue({
      success: true,
      message: 'Created',
      data: mockLocations[0],
    } as any)

    renderComponent()

    const addBtn = await screen.findByRole('button', { name: /tambah lokasi baru/i })
    fireEvent.click(addBtn)

    fireEvent.change(screen.getByLabelText(/nama proyek \/ area/i), { target: { value: 'Tol Serpong Balaraja' } })
    fireEvent.change(screen.getByLabelText(/nama pic lapangan/i), { target: { value: 'Dedi Kusnadi' } })
    fireEvent.change(screen.getByLabelText(/nomor telepon pic/i), { target: { value: '0811223344' } })
    fireEvent.change(screen.getByLabelText(/kota \/ kabupaten/i), { target: { value: 'Tangerang' } })
    fireEvent.change(screen.getByLabelText(/alamat lengkap proyek/i), { target: { value: 'KM 12 Tol Serpong' } })

    const submitBtn = screen.getByRole('button', { name: /daftarkan lokasi/i })
    fireEvent.click(submitBtn)

    await waitFor(() => {
      expect(projectLocationService.createLocation).toHaveBeenCalledWith(expect.objectContaining({
        project_name: 'Tol Serpong Balaraja',
        pic_name: 'Dedi Kusnadi',
      }))
    })
  })

  it('renders empty state when no locations registered', async () => {
    vi.mocked(projectLocationService.getLocations).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 9, total: 0, last_page: 1 },
    } as any)

    renderComponent()

    await waitFor(() => {
      expect(screen.getByText(/belum ada lokasi proyek/i)).toBeInTheDocument()
    })
  })
})
