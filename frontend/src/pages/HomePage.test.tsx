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
  equipmentService: { getTypes: vi.fn() },
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
    expect(screen.getByText('Cari Equipment')).toBeInTheDocument()
    const img = screen.getByAltText('Armada alat berat RAFA Rental') as HTMLImageElement
    expect(img.src).toContain('hero-equipment.svg')
    expect(screen.queryByText('Gambar Armada')).not.toBeInTheDocument()
  })
})