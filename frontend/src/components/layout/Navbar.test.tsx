import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor, within } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { Navbar } from './Navbar'
import { AuthProvider } from '@/app/AuthContext'
import { cmsService } from '@/features/cms/services/cmsService'

vi.mock('@/features/cms/services/cmsService', () => ({
  cmsService: { getPublic: vi.fn() },
}))
vi.mock('@/features/auth/services/authService', () => ({
  authService: { getMe: vi.fn(), login: vi.fn(), logout: vi.fn(), register: vi.fn(), requestPasswordReset: vi.fn(), resetPassword: vi.fn() },
}))

const renderNav = (path = '/') =>
  render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <Navbar />
      </AuthProvider>
    </MemoryRouter>
  )

describe('Public Navbar — redesign & navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(cmsService.getPublic).mockResolvedValue({})
  })

  it('shows default menu links (Beranda/Equipment/Tentang Kami/Kontak) + Masuk/Daftar guest actions', async () => {
    renderNav()

    expect(await screen.findByText('CV SUMBER MAKMUR RAFA')).toBeInTheDocument()
    for (const label of ['Beranda', 'Equipment', 'Tentang Kami', 'Kontak']) {
      expect(screen.getByText(label)).toBeInTheDocument()
    }
    expect(screen.getByText('Masuk')).toBeInTheDocument()
    expect(screen.getByText('Daftar')).toBeInTheDocument()
  })

  it('mobile hamburger opens the navigation panel and closes after choosing a destination', async () => {
    renderNav()

    const toggle = (await screen.findAllByRole("button", { name: /buka menu navigasi/i })).at(-1) as HTMLElement
    fireEvent.click(toggle)

    const panel = await screen.findByRole('navigation', { name: /navigasi mobile/i })
    expect(panel).toBeInTheDocument()

    fireEvent.click(within(panel).getByText('Equipment'))
    await waitFor(() => {
      expect(screen.queryByRole('navigation', { name: /navigasi mobile/i })).not.toBeInTheDocument()
    })
  })

  it('closes the mobile panel with the Escape key', async () => {
    renderNav()

    fireEvent.click((await screen.findAllByRole("button", { name: /buka menu navigasi/i })).at(-1) as HTMLElement)
    expect(await screen.findByRole('navigation', { name: /navigasi mobile/i })).toBeInTheDocument()

    fireEvent.keyDown(window, { key: 'Escape' })
    await waitFor(() => {
      expect(screen.queryByRole('navigation', { name: /navigasi mobile/i })).not.toBeInTheDocument()
    })
  })

  it('marks the active home menu item when on the landing page (blue active state)', async () => {
    renderNav('/')

    const active = (await screen.findByText('Beranda')).closest('button')
    expect(active?.className).toContain('text-accent-600')
  })
})
