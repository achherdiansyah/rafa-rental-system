import React, { useState } from 'react'
import { Link } from 'react-router-dom'
import { authService } from '@/features/auth/services/authService'
import { Input } from '@/components/form/Input'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/feedback/Alert'
import type { ApiError } from '@/types/api'

export const ForgotPasswordPage: React.FC = () => {
  const [email, setEmail] = useState('')
  const [isSubmitted, setIsSubmitted] = useState(false)
  const [isLoading, setIsLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setIsLoading(true)

    try {
      await authService.forgotPassword(email)
      setIsSubmitted(true)
    } catch (err) {
      const apiError = err as ApiError
      setError(apiError.message || 'Gagal mengirim permintaan reset kata sandi. Silakan coba lagi.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <div className="space-y-4">
      <div>
        <h3 className="text-xl font-bold text-slate-900">Reset Password</h3>
        <p className="text-sm text-slate-500 mt-1">
          Masukkan alamat email Anda untuk menerima tautan pemulihan kata sandi.
        </p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      {isSubmitted ? (
        <div className="space-y-4">
          <Alert variant="success" title="Permintaan Terkirim">
            Instruksi pemulihan kata sandi telah dikirimkan ke <strong>{email}</strong> bila terdaftar di sistem.
          </Alert>
          <Link to="/login" className="block text-center text-sm font-medium text-primary-600 hover:underline">
            Kembali ke halaman login
          </Link>
        </div>
      ) : (
        <form onSubmit={handleSubmit} className="space-y-4">
          <Input
            label="Email Terdaftar"
            type="email"
            required
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            placeholder="nama@perusahaan.com"
          />

          <Button type="submit" size="lg" isLoading={isLoading} className="w-full">
            Kirim Tautan Reset
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
export default ForgotPasswordPage
