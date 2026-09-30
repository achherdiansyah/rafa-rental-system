import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { RoleSidebar } from './sidebar/RoleSidebar'
import { userMenu, adminMenu, ownerMenu } from './sidebarNavigation'
import { AuthProvider } from '@/app/AuthContext'
import { CmsProvider } from '@/features/cms/CmsContext'
import { cmsService } from '@/features/cms/services/cmsService'

vi.mock('@/features/cms/services/cmsService', () => ({
  cmsService: { getPublic: vi.fn() },
}))
vi.mock('@/features/auth/services/authService', () => ({
  authService: { getMe: vi.fn(), login: vi.fn(), logout: vi.fn(), register: vi.fn(), requestPasswordReset: vi.fn(), resetPassword: vi.fn() },
}))

const renderSidebar = (groups: typeof userMenu, path = '/app/equipment', isOpen = false) =>
  render(
    <MemoryRouter initialEntries={[path]}>
      <AuthProvider>
        <CmsProvider>
          <RoleSidebar groups={groups} title="Test" isOpen={isOpen} onClose={() => undefined} />
        </CmsProvider>
      </AuthProvider>
    </MemoryRouter>
  )

describe('RoleSidebar grouped navigation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(cmsService.getPublic).mockResolvedValue({})
  })

  it('renders grouped sections and all items for USER', async () => {
    renderSidebar(userMenu)
    await waitFor(() => expect(screen.getByText('Utama')).toBeInTheDocument())
    for (const label of ['Dashboard', 'Katalog Alat', 'Keranjang', 'Lokasi Proyek', 'Tagihan & Bayar', 'Notifikasi', 'Profil']) {
      expect(screen.getByText(label)).toBeInTheDocument()
    }
  })

  it('highlights the active menu item with the light-blue style', async () => {
    renderSidebar(userMenu)
    await waitFor(() => expect(screen.getByText('Katalog Alat')).toBeInTheDocument())
    const link = screen.getByText('Katalog Alat').closest('a') as HTMLElement
    expect(link.className).toContain('bg-primary-50')
    expect(link.className).toContain('text-primary-700')
  })

  it('collapses to icon-only width with tooltips', async () => {
    renderSidebar(userMenu)
    await waitFor(() => {
      fireEvent.click(screen.getByRole('button', { name: /ciutkan menu/i }))
    })
    const aside = document.querySelector('aside') as HTMLElement
    expect(aside.className).toContain('w-[72px]')
    expect(screen.queryByText('Utama')).not.toBeInTheDocument()
  })

  it('opens as a mobile drawer when isOpen=true', async () => {
    renderSidebar(userMenu, '/app', true)
    await waitFor(() => expect(screen.getAllByText('Katalog Alat').length).toBeGreaterThanOrEqual(1))
    expect(document.querySelector('.fixed.inset-0.z-50')).not.toBeNull()
  })

  it('OWNER menu has no pricing entry, ADMIN menu has pricing', async () => {
    const ownerLabels = ownerMenu.flatMap((g) => g.items.map((i) => i.label))
    expect(ownerLabels).not.toContain('Master Tarif & Harga')
    const adminLabels = adminMenu.flatMap((g) => g.items.map((i) => i.label))
    expect(adminLabels).toContain('Tarif & Harga')
  })
})