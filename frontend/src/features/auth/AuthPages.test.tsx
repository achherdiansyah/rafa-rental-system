import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { BrowserRouter } from 'react-router-dom'
import { LoginPage } from '@/pages/LoginPage'
import { RegisterPage } from '@/pages/RegisterPage'
import { ForgotPasswordPage } from '@/pages/ForgotPasswordPage'
import { ResetPasswordPage } from '@/pages/ResetPasswordPage'
import { AuthProvider } from '@/app/AuthContext'

vi.mock('@/features/auth/services/authService', () => ({
  authService: {
    login: vi.fn(),
    register: vi.fn(),
    logout: vi.fn(),
    getMe: vi.fn(),
    forgotPassword: vi.fn().mockResolvedValue('Tautan reset terkirim'),
    resetPassword: vi.fn().mockResolvedValue('Password diperbarui'),
  },
}))

const renderWithProviders = (component: React.ReactNode) => {
  return render(
    <BrowserRouter>
      <AuthProvider>{component}</AuthProvider>
    </BrowserRouter>
  )
}

describe('Authentication UI Pages', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('renders Login page with email and password inputs', () => {
    renderWithProviders(<LoginPage />)

    expect(screen.getByRole('heading', { name: /masuk akun/i })).toBeInTheDocument()
    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/^kata sandi/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /masuk/i })).toBeInTheDocument()
  })

  it('renders Register page and blocks mismatching password confirmation', async () => {
    renderWithProviders(<RegisterPage />)

    expect(screen.getByRole('heading', { name: /daftar akun baru/i })).toBeInTheDocument()
    
    fireEvent.change(screen.getByLabelText(/nama lengkap/i), { target: { value: 'Budi' } })
    fireEvent.change(screen.getByLabelText(/email/i), { target: { value: 'budi@test.com' } })
    fireEvent.change(screen.getByLabelText(/nomor telepon/i), { target: { value: '08123456789' } })
    fireEvent.change(screen.getByLabelText(/^kata sandi/i), { target: { value: 'secret123' } })
    fireEvent.change(screen.getByLabelText(/ulangi kata sandi/i), { target: { value: 'mismatch456' } })
    fireEvent.change(screen.getByLabelText(/nomor ktp/i), { target: { value: '320101' } })

    fireEvent.click(screen.getByRole('button', { name: /daftar sekarang/i }))

    await waitFor(() => {
      expect(screen.getByText(/konfirmasi kata sandi tidak cocok/i)).toBeInTheDocument()
    })
  })

  it('renders ForgotPassword page and submits request', async () => {
    renderWithProviders(<ForgotPasswordPage />)

    expect(screen.getByRole('heading', { name: /reset password/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/email terdaftar/i), { target: { value: 'user@example.com' } })
    fireEvent.click(screen.getByRole('button', { name: /kirim tautan reset/i }))

    await waitFor(() => {
      expect(screen.getByText(/instruksi pemulihan kata sandi telah dikirimkan/i)).toBeInTheDocument()
    })
  })

  it('renders ResetPassword page and validates minimum length', async () => {
    renderWithProviders(<ResetPasswordPage />)

    expect(screen.getByRole('heading', { name: /perbarui kata sandi/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/email terdaftar/i), { target: { value: 'user@example.com' } })
    fireEvent.change(screen.getByLabelText(/^kata sandi baru/i), { target: { value: 'short' } })
    fireEvent.change(screen.getByLabelText(/ulangi kata sandi baru/i), { target: { value: 'short' } })

    fireEvent.click(screen.getByRole('button', { name: /simpan kata sandi baru/i }))

    await waitFor(() => {
      expect(screen.getByText(/kata sandi harus minimal 8 karakter/i)).toBeInTheDocument()
    })
  })
})
