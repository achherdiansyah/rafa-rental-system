import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { PublicLayout } from './PublicLayout'
import { AuthProvider } from '@/app/AuthContext'
import { cmsService } from '@/features/cms/services/cmsService'

vi.mock('@/features/cms/services/cmsService', () => ({
  cmsService: { getPublic: vi.fn() },
}))

vi.mock('@/features/auth/services/authService', () => ({
  authService: { getMe: vi.fn(), login: vi.fn(), logout: vi.fn(), register: vi.fn(), requestPasswordReset: vi.fn(), resetPassword: vi.fn() },
}))

const renderLayout = () =>
  render(
    <MemoryRouter initialEntries={['/']}>
      <AuthProvider>
        <PublicLayout />
      </AuthProvider>
    </MemoryRouter>
  )

describe('PublicLayout brand/navbar CMS sync', () => {
  beforeEach(() => vi.clearAllMocks())

  it('renders CMS logo, brand name, navbar menu and footer brand on the landing chrome', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({
      brand_name: 'RAFA Premium Rental',
      brand_logo: 'http://s/storage/cms/logo.png',
      navbar: JSON.stringify([
        { label: 'Beranda', href: '/' },
        { label: 'Katalog', href: '/app/equipment' },
      ]),
      footer: 'RAFA Premium Rental',
    })

    renderLayout()

    expect((await screen.findAllByText('RAFA Premium Rental')).length).toBeGreaterThanOrEqual(1)
    expect((await screen.findAllByAltText('RAFA Premium Rental')).length).toBeGreaterThanOrEqual(1)
    expect((await screen.findAllByText('Katalog')).length).toBeGreaterThanOrEqual(1)
    expect((await screen.findAllByText('Beranda')).length).toBeGreaterThanOrEqual(1)
    await waitFor(() =>
      expect(screen.getByText(new RegExp('©.*RAFA Premium Rental'))).toBeInTheDocument()
    )
  })

  it('falls back to the default brand when CMS is empty', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderLayout()

    expect((await screen.findAllByText('CV SUMBER MAKMUR RAFA')).length).toBeGreaterThanOrEqual(1)
  })
})
