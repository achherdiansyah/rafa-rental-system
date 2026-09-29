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

    expect(await screen.findByText('RAFA Premium Rental')).toBeInTheDocument()
    expect(screen.getByAltText('RAFA Premium Rental')).toBeInTheDocument()
    expect(screen.getByText('Katalog')).toBeInTheDocument()
    expect(screen.getByText('Beranda')).toBeInTheDocument()
    await waitFor(() => expect(screen.getByText(/RAFA Premium Rental\. All rights/i)).toBeInTheDocument())
  })

  it('falls back to the default brand when CMS is empty', async () => {
    vi.mocked(cmsService.getPublic).mockResolvedValue({})

    renderLayout()

    expect(await screen.findByText('CV SUMBER MAKMUR RAFA')).toBeInTheDocument()
  })
})
