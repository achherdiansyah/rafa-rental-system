import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { AdminPoolsPage } from './pages/AdminPoolsPage'
import { equipmentService } from './services/equipmentService'
import { ToastProvider } from '@/app/ToastContext'
import type { EquipmentPool } from '@/types/equipment'

vi.mock('@/features/location/RegionSelects', () => ({
  RegionSelects: ({ onChange }: any) => (<button type="button" onClick={() => onChange({ province_code: '32', province_name: 'Jawa Barat', regency_code: '32.73', regency_name: 'Kota Bandung', district_code: '32.73.01', district_name: 'Sukasari', village_code: '32.73.01.1001', village_name: 'Sukarasa' })}>Pilih Wilayah</button>),
}))
vi.mock('@/features/location/MapLocationPicker', () => ({
  MapLocationPicker: ({ onChange }: any) => (<button type="button" onClick={() => onChange(-7.3275, 112.7122)}>Pilih Titik</button>),
}))
vi.mock('./services/equipmentService', () => ({
  equipmentService: {
    getPools: vi.fn(),
    createPool: vi.fn(),
    updatePool: vi.fn(),
    deletePool: vi.fn(),
  },
}))

const renderPage = () =>
  render(
    <MemoryRouter>
      <ToastProvider>
        <AdminPoolsPage />
      </ToastProvider>
    </MemoryRouter>
  )

const pool: EquipmentPool = {
  id: 3,
  name: 'Pool Bandung',
  address: 'Jl. Contoh No. 1',
  city: 'Bandung',
  province_code: '32', province_name: 'Jawa Barat', regency_code: '32.73', regency_name: 'Kota Bandung', district_code: '32.73.01', district_name: 'Sukasari', village_code: '32.73.01.1001', village_name: 'Sukarasa',
  latitude: -7.3275,
  longitude: 112.7122,
  is_active: true,
}

describe('Admin pool coordinate inputs', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(equipmentService.getPools).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [],
      meta: { current_page: 1, per_page: 100, total: 0, last_page: 1 },
    } as any)
    vi.mocked(equipmentService.createPool).mockResolvedValue({ success: true, message: 'OK', data: pool } as any)
    vi.mocked(equipmentService.updatePool).mockResolvedValue({ success: true, message: 'OK', data: pool } as any)
  })

  it('uses manual numeric inputs with format placeholders and rejects 0,0', async () => {
    renderPage()

    fireEvent.click(await screen.findByRole('button', { name: /tambah pool/i }))
    const dialog = screen.getByRole('dialog')

    const latitude = within(dialog).getByPlaceholderText('Contoh: -7.3275')
    const longitude = within(dialog).getByPlaceholderText('Contoh: 112.7122')
    expect(latitude).toHaveAttribute('type', 'number')
    expect(longitude).toHaveAttribute('type', 'number')

    fireEvent.change(within(dialog).getByLabelText(/nama pool/i), { target: { value: 'Pool Baru' } })
    fireEvent.change(within(dialog).getByLabelText(/alamat/i), { target: { value: 'Jl. Test' } })
    fireEvent.change(latitude, { target: { value: '0' } })
    fireEvent.change(longitude, { target: { value: '0' } })

    fireEvent.click(within(dialog).getByRole('button', { name: /^tambah pool$/i }))

    expect((await screen.findAllByText(/koordinat 0,0 tidak dapat dipakai/i)).length).toBeGreaterThanOrEqual(1)
    expect(equipmentService.createPool).not.toHaveBeenCalled()

    fireEvent.click(within(dialog).getByRole('button', { name: /pilih wilayah/i }))
    fireEvent.click(within(dialog).getByRole('button', { name: /pilih titik/i }))
    fireEvent.change(latitude, { target: { value: '-7.3275' } })
    fireEvent.change(longitude, { target: { value: '112.7122' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /^tambah pool$/i }))

    await waitFor(() => {
      expect(equipmentService.createPool).toHaveBeenCalledWith(expect.objectContaining({
        latitude: -7.3275,
        longitude: 112.7122,
      }))
    })
  })

  it('prefills edit form with negative decimal values and saves numbers', async () => {
    vi.mocked(equipmentService.getPools).mockResolvedValue({
      success: true,
      message: 'OK',
      data: [pool],
      meta: { current_page: 1, per_page: 100, total: 1, last_page: 1 },
    } as any)

    renderPage()

    fireEvent.click(await screen.findByTitle('Edit pool'))
    const dialog = screen.getByRole('dialog')

    expect(within(dialog).getByDisplayValue('-7.3275')).toBeInTheDocument()
    expect(within(dialog).getByDisplayValue('112.7122')).toBeInTheDocument()

    fireEvent.click(within(dialog).getByRole('button', { name: /simpan perubahan/i }))

    await waitFor(() => {
      expect(equipmentService.updatePool).toHaveBeenCalledWith(3, expect.objectContaining({
        latitude: -7.3275,
        longitude: 112.7122,
      }))
    })
  })

  it('shows clear validation when coordinates are empty or out of range', async () => {
    renderPage()
    fireEvent.click(await screen.findByRole('button', { name: /tambah pool/i }))
    const dialog = screen.getByRole('dialog')

    fireEvent.change(within(dialog).getByLabelText(/nama pool/i), { target: { value: 'Pool Baru' } })
    fireEvent.change(within(dialog).getByLabelText(/alamat/i), { target: { value: 'Jl. Test' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /^tambah pool$/i }))

    expect(await screen.findByText('Latitude wajib diisi.')).toBeInTheDocument()
    expect(screen.getByText('Longitude wajib diisi.')).toBeInTheDocument()
    expect(equipmentService.createPool).not.toHaveBeenCalled()

    fireEvent.change(within(dialog).getByLabelText(/latitude/i), { target: { value: '95' } })
    fireEvent.change(within(dialog).getByLabelText(/longitude/i), { target: { value: '200' } })
    fireEvent.click(within(dialog).getByRole('button', { name: /^tambah pool$/i }))

    expect(await screen.findByText(/latitude harus berupa angka antara -90 dan 90/i)).toBeInTheDocument()
    expect(screen.getByText(/longitude harus berupa angka antara -180 dan 180/i)).toBeInTheDocument()
  })
})

