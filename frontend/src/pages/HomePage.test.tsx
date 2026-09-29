import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { HomePage } from './HomePage'
import { CmsProvider } from '@/features/cms/CmsContext'
import { cmsService } from '@/features/cms/services/cmsService'

vi.mock('@/features/cms/services/cmsService', () => ({
  cmsService: { getPublic: vi.fn() },
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
  beforeEach(() => vi.clearAllMocks())

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
    const img = screen.getByAltText('Hero RAFA Rental') as HTMLImageElement
    expect(img.src).toContain('/storage/cms/hero.png')
  })

  it('falls back to defaults when CMS is empty', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderHome()

    expect(await screen.findByText(/Sewa Armada Alat Berat Mudah, Akurat & Transparan/)).toBeInTheDocument()
    expect(screen.getByText('Mulai Sewa Sekarang')).toBeInTheDocument()
  })
})