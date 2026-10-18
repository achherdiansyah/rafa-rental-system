import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import { OperatorAssignmentDetailPage } from './pages/OperatorAssignmentDetailPage'
import { OperatorAssignmentsPage } from './pages/OperatorAssignmentsPage'
import { operatorService } from './services/operatorService'
import type { OperatorAssignment } from './services/operatorService'

vi.mock('./services/operatorService', () => ({
  operatorService: {
    getAssignment: vi.fn(),
    getAssignments: vi.fn(),
    getDashboard: vi.fn(),
  },
}))

const baseAssignment: OperatorAssignment = {
  id: 12,
  rental_id: 5,
  rental_detail_id: 77,
  rental_status: 'ASSIGNED',
  booking_code: 'RFA-BKG-20261001-0001',
  project_location: {
    id: 3,
    project_name: 'Proyek Bendungan Leuwikeris',
    address: 'Kecamatan Ciamis, Kabupaten Ciamis',
    city: 'Ciamis',
    latitude: -6.914744,
    longitude: 107.60981,
    pic_name: 'Agus Priyanto',
    pic_phone: '081399887702',
  },
  unit: { id: 9, serial_number: 'KM-D85-001', plate_number: 'B 9301 RFA', status: 'ASSIGNED' },
  status: 'ACTIVE',
  started_at: '2026-10-01T08:00:00Z',
  ended_at: null,
}

const renderDetail = () =>
  render(
    <MemoryRouter initialEntries={['/operator/assignments/12']}>
      <Routes>
        <Route path="/operator/assignments/:id" element={<OperatorAssignmentDetailPage />} />
      </Routes>
    </MemoryRouter>
  )

describe('Operator project location view', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('shows address, coordinates and map/navigation links from own assignment', async () => {
    vi.mocked(operatorService.getAssignment).mockResolvedValue({ success: true, message: 'ok', data: baseAssignment } as any)

    renderDetail()

    await waitFor(() => expect(screen.getByText('Proyek Bendungan Leuwikeris')).toBeInTheDocument())
    expect(screen.getByText('Kecamatan Ciamis, Kabupaten Ciamis')).toBeInTheDocument()
    expect(screen.getByText('Ciamis')).toBeInTheDocument()
    expect(screen.getByText(/Koordinat: -6.914744, 107.60981/)).toBeInTheDocument()

    const navLink = screen.getByRole('link', { name: /navigasi ke lokasi/i })
    expect(navLink).toHaveAttribute('href', expect.stringContaining('google.com/maps/dir/?api=1&destination=-6.914744,107.60981'))
    const mapLink = screen.getByRole('link', { name: /lihat titik peta/i })
    expect(mapLink).toHaveAttribute('href', expect.stringContaining('google.com/maps?q=-6.914744,107.60981'))
  })

  it('does not invent a map point when coordinates are missing', async () => {
    vi.mocked(operatorService.getAssignment).mockResolvedValue({
      success: true,
      message: 'ok',
      data: { ...baseAssignment, project_location: { ...baseAssignment.project_location!, latitude: null, longitude: null } },
    } as any)

    renderDetail()

    await waitFor(() => expect(screen.getByText('Proyek Bendungan Leuwikeris')).toBeInTheDocument())
    expect(screen.queryByRole('link', { name: /lihat titik peta/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('link', { name: /navigasi ke lokasi/i })).not.toBeInTheDocument()
    expect(screen.getByText(/Titik koordinat belum tersedia/i)).toBeInTheDocument()
  })

  it('shows an informative empty state when the operator has no assignment', async () => {
    vi.mocked(operatorService.getAssignments).mockResolvedValue({ success: true, message: 'ok', data: [] } as any)

    render(
      <MemoryRouter>
        <OperatorAssignmentsPage />
      </MemoryRouter>
    )

    await waitFor(() => expect(screen.getByText(/Belum Ada Penugasan/i)).toBeInTheDocument())
  })

  it('does not render another operator assignment data when lookup fails', async () => {
    vi.mocked(operatorService.getAssignment).mockRejectedValue({ message: 'Forbidden', status: 403 })

    renderDetail()

    await waitFor(() => expect(screen.getByText(/Gagal Memuat Detail/i)).toBeInTheDocument())
    expect(screen.queryByText('Proyek Bendungan Leuwikeris')).not.toBeInTheDocument()
  })
})
