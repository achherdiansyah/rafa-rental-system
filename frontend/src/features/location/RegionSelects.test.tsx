import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import React, { useState } from 'react'
import { RegionSelects } from './RegionSelects'
import { regionService, emptyRegion } from './regionService'
import type { RegionValue } from './regionService'

vi.mock('./regionService', async () => {
  const actual = await vi.importActual<typeof import('./regionService')>('./regionService')
  return {
    ...actual,
    regionService: {
      provinces: vi.fn(),
      regencies: vi.fn(),
      districts: vi.fn(),
      villages: vi.fn(),
    },
  }
})

const Harness: React.FC = () => {
  const [value, setValue] = useState<RegionValue>(emptyRegion)
  return <RegionSelects value={value} onChange={setValue} />
}

describe('RegionSelects hierarchy', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(regionService.provinces).mockResolvedValue([
      { id: '32', name: 'Jawa Barat' },
      { id: '35', name: 'Jawa Timur' },
    ])
    vi.mocked(regionService.regencies).mockResolvedValue([
      { id: '32.73', name: 'Kota Bandung' },
    ])
    vi.mocked(regionService.districts).mockResolvedValue([
      { id: '32.73.01', name: 'Sukasari' },
    ])
    vi.mocked(regionService.villages).mockResolvedValue([
      { id: '32.73.01.1001', name: 'Sukarasa' },
    ])
  })

  it('loads children in order and resets descendants when the parent changes', async () => {
    render(<Harness />)

    const province = await screen.findByLabelText('Provinsi *')
    const regency = screen.getByLabelText('Kabupaten/Kota *')
    expect(regency).toBeDisabled()

    fireEvent.change(province, { target: { value: '32' } })
    await waitFor(() => expect(regionService.regencies).toHaveBeenCalledWith('32'))
    expect(await screen.findByText('Kota Bandung')).toBeInTheDocument()

    fireEvent.change(regency, { target: { value: '32.73' } })
    await waitFor(() => expect(regionService.districts).toHaveBeenCalledWith('32.73'))
    expect(await screen.findByText('Sukasari')).toBeInTheDocument()

    const district = screen.getByLabelText('Kecamatan *')
    fireEvent.change(district, { target: { value: '32.73.01' } })
    await waitFor(() => expect(regionService.villages).toHaveBeenCalledWith('32.73.01'))
    expect(await screen.findByText('Sukarasa')).toBeInTheDocument()

    const village = screen.getByLabelText('Desa/Kelurahan *')
    expect(village).toHaveValue('')
    fireEvent.change(village, { target: { value: '32.73.01.1001' } })

    // Changing province resets regency/district/village.
    fireEvent.change(province, { target: { value: '35' } })
    await waitFor(() => expect(screen.getByLabelText('Kabupaten/Kota *')).toHaveValue(''))
    expect(screen.getByLabelText('Kecamatan *')).toHaveValue('')
    expect(screen.getByLabelText('Desa/Kelurahan *')).toHaveValue('')
  })

  it('shows an error and retry when province loading fails', async () => {
    vi.mocked(regionService.provinces).mockRejectedValueOnce({ message: 'Gagal memuat wilayah.' })
    render(<Harness />)

    expect(await screen.findByText('Gagal memuat wilayah.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: /coba lagi/i }))
    expect(await screen.findByText('Jawa Barat')).toBeInTheDocument()
  })
})
