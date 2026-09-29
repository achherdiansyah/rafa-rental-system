import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { HomePage } from './HomePage'
import { CmsProvider } from '@/features/cms/CmsContext'
import { cmsService } from '@/features/cms/services/cmsService'
import { equipmentService } from '@/features/equipment/services/equipmentService'

vi.mock('@/features/cms/services/cmsService', () => ({
  cmsService: { getPublic: vi.fn() },
}))

vi.mock('@/features/equipment/services/equipmentService', () => ({
  equipmentService: { getTypes: vi.fn(), getModels: vi.fn() },
}))

const renderHome = () =>
  render(
    <MemoryRouter>
      <CmsProvider>
        <HomePage />
      </CmsProvider>
    </MemoryRouter>
  )

describe('HomePage CMS sync', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(equipmentService.getTypes).mockResolvedValue({
      success: true,
      message: 'ok',
      data: [],
    } as never)
    vi.mocked(equipmentService.getModels).mockResolvedValue({
      success: true,
      message: 'ok',
      data: [],
      meta: { current_page: 1, per_page: 6, total: 0, last_page: 1 } as never,
    } as never)
  })

  it('renders admin-set hero content from the public CMS API (no defaults)', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({
      hero_title: 'Sewa Alat Profesional',
      hero_subtitle: 'Subtitle dari CMS',
      hero_cta_text: 'Mulai Sekarang',
      hero_cta_link: '/register',
      hero_image: 'http://s/storage/cms/hero.png',
    })

    renderHome()

    expect(await screen.findByText('Sewa Alat Profesional')).toBeInTheDocument()
    expect(screen.getByText('Subtitle dari CMS')).toBeInTheDocument()
    expect(screen.getByText('Mulai Sekarang')).toBeInTheDocument()
    const img = screen.getByAltText('Armada alat berat RAFA Rental') as HTMLImageElement
    expect(img.src).toContain('/storage/cms/hero.png')
  })

  it('falls back to defaults + local hero asset when CMS is empty', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderHome()

    expect(await screen.findByText('Sewa Alat Berat untuk Proyek Anda')).toBeInTheDocument()
    expect(screen.getAllByText('Cari Equipment').length).toBeGreaterThanOrEqual(1)
    const img = screen.getByAltText('Armada alat berat RAFA Rental') as HTMLImageElement
    expect(img.src).toContain('hero-equipment.svg')
    expect(screen.queryByText('Gambar Armada')).not.toBeInTheDocument()
  })

  it('renders the statistics strip using the specified constants', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderHome()

    expect(await screen.findByText('Unit Alat Berat')).toBeInTheDocument()
    expect(screen.getByText('100+')).toBeInTheDocument()
    expect(screen.getByText('Proyek Terlayani')).toBeInTheDocument()
    expect(screen.getByText('Dukungan Pelanggan')).toBeInTheDocument()
  })

  it('shows the featured-equipment fallback list when the API returns no data', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderHome()

    // fallback rows (specified constants; no invented availability)
    expect(await screen.findByText('Excavator PC200')).toBeInTheDocument()
    expect(screen.getByText('Dump Truck HD785')).toBeInTheDocument()
    expect(screen.getByText('Rp 2.500.000 / hari')).toBeInTheDocument()
  })
})