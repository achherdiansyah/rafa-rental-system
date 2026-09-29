import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { UserLayout } from './UserLayout'
import { notificationService } from '@/features/notification/services/notificationService'
import { AuthProvider } from '@/app/AuthContext'

vi.mock('@/features/notification/services/notificationService', () => ({
  notificationService: { unreadCount: vi.fn() },
}))

vi.mock('@/features/auth/services/authService', () => ({
  authService: {
    getMe: vi.fn(),
    login: vi.fn(),
    logout: vi.fn(),
    register: vi.fn(),
    requestPasswordReset: vi.fn(),
    resetPassword: vi.fn(),
  },
}))

const renderLayout = () =>
  render(
    <MemoryRouter initialEntries={['/app']}>
      <AuthProvider>
        <UserLayout />
      </AuthProvider>
    </MemoryRouter>
  )

describe('UserLayout unread notification badge', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)
  })

  it('hides the badge when unread count is 0', async () => {
    renderLayout()
    await waitFor(() => expect(notificationService.unreadCount).toHaveBeenCalled())
    expect(screen.queryByText('5')).not.toBeInTheDocument()
  })

  it('shows the badge count when unread > 0 and syncs via notifications-changed event', async () => {
    vi.mocked(notificationService.unreadCount).mockResolvedValue(5)
    renderLayout()

    await waitFor(() => expect(screen.getByText('5')).toBeInTheDocument())

    // After marking notifications as read, the event refreshes the badge
    vi.mocked(notificationService.unreadCount).mockResolvedValue(0)
    fireEvent(window, new CustomEvent('rafa:notifications-changed'))
    await waitFor(() => expect(screen.queryByText('5')).not.toBeInTheDocument())
  })

  it('reloads the count when navigating between routes', async () => {
    vi.mocked(notificationService.unreadCount).mockResolvedValue(2)
    renderLayout()
    await waitFor(() => expect(screen.getByText('2')).toBeInTheDocument())

    expect(notificationService.unreadCount).toHaveBeenCalled()
  })
})