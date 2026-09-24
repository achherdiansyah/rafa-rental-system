import React, { useState } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { authService } from '@/features/auth/services/authService'
import { PasswordInput } from '@/components/form/PasswordInput'
import { Input } from '@/components/form/Input'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/feedback/Alert'
import type { ApiError } from '@/types/api'

export const ResetPasswordPage: React.FC = () => {
  const location = useLocation()
  const queryParams = new URLSearchParams(location.search)
  const defaultEmail = queryParams.get('email') || ''
  const token = queryParams.get('token') || ''

  const [email, setEmail] = useState(defaultEmail)
  const [password, setPassword] = useState('')
  const [passwordConfirm, setPasswordConfirm] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isSuccess, setIsSuccess] = useState(false)
  const [isLoading, setIsLoading] = useState(false)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)

    if (password !== passwordConfirm) {
      setError('Konfirmasi kata sandi tidak cocok.')
      return
    }

    if (password.length < 8) {
      setError('Kata sandi harus minimal 8 karakter.')
      return
    }

    if (!token) {
      setError('Token reset kata sandi tidak ditemukan pada tautan URL.')
      return
    }

    setIsLoading(true)

    try {
      await authService.resetPassword({
        token,
        email,
        password,
        password_confirmation: passwordConfirm,
      })
      setIsSuccess(true)
    } catch (err) {
      const apiError = err as ApiError
      setError(apiError.message || 'Gagal memperbarui kata sandi. Tautan mungkin telah kedaluwarsa.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-xl font-bold text-slate-900">Perbarui Kata Sandi</h3>
        <p className="text-sm text-slate-500 mt-1">
          Masukkan kata sandi baru untuk akun Anda.
        </p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {isSuccess ? (
        <div className="space-y-4">
          <Alert variant="success" title="Kata Sandi Diperbarui">
            Kata sandi Anda berhasil diperbarui. Silakan masuk menggunakan kata sandi baru.
          </Alert>
          <Link to="/login" className="block text-center text-sm font-semibold text-primary-600 hover:underline">
            Masuk ke Akun
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <input type="hidden" value={token} />

          <Input
            label="Email Terdaftar"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="nama@perusahaan.com"
          />

          <PasswordInput
            label="Kata Sandi Baru"
            required
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            placeholder="Minimal 8 karakter"
          />

          <PasswordInput
            label="Ulangi Kata Sandi Baru"
            required
            value={passwordConfirm}
            onChange={(e) => setPasswordConfirm(e.target.value)}
            placeholder="Konfirmasi kata sandi baru"
          />

          <Button type="submit" size="lg" isLoading={isLoading} className="w-full mt-2">
            Simpan Kata Sandi Baru
          </Button>

          <div className="text-center text-sm text-slate-500">
            <Link to="/login" className="text-primary-600 hover:underline font-medium">
              Batal dan kembali ke login
            </Link>
          </div>
        </form>
      )}
    </div>
  )
}
export default ResetPasswordPage
