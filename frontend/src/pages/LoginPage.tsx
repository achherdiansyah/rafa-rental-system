import React, { useState } from 'react'
import { Link, useNavigate, useLocation } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { Input } from '@/components/form/Input'
import { PasswordInput } from '@/components/form/PasswordInput'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/feedback/Alert'
import type { ApiError } from '@/types/api'

export const LoginPage: React.FC = () => {
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState<string | null>(null)
  const [isLoading, setIsLoading] = useState(false)
  const { login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const queryParams = new URLSearchParams(location.search)
  const isExpired = queryParams.get('expired') === '1'

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setIsLoading(true)

    try {
      const user = await login({ email, password })

      const from = (location.state as { from?: { pathname: string } })?.from?.pathname

      if (from) {
        navigate(from, { replace: true })
      } else if (user.role === 'ADMIN') {
        navigate('/admin', { replace: true })
      } else if (user.role === 'OWNER') {
        navigate('/owner', { replace: true })
      } else {
        navigate('/app', { replace: true })
      }
    } catch (err) {
      const apiError = err as ApiError
      setError(apiError.message || 'Login gagal. Periksa kembali email dan kata sandi Anda.')
    } finally {
      setIsLoading(false)
    }
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-5">
      <div>
        <h3 className="text-xl font-bold text-slate-900">Masuk Akun</h3>
        <p className="text-sm text-slate-500 mt-1">Masukkan email dan kata sandi terdaftar Anda</p>
      </div>

      {isExpired && (
        <Alert variant="warning" title="Sesi Berakhir">
          Sesi Anda telah kedaluwarsa. Silakan masuk kembali.
        </Alert>
      )}

      {error && <Alert variant="danger">{error}</Alert>}

      <Input
        label="Email"
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        placeholder="nama@perusahaan.com"
      />

      <PasswordInput
        label="Kata Sandi"
        required
        value={password}
        onChange={(e) => setPassword(e.target.value)}
        placeholder="••••••••"
      />

      <div className="flex items-center justify-between text-sm">
        <span className="text-slate-500 text-xs">Sanctum Token Auth</span>
        <Link to="/forgot-password" className="text-primary-600 hover:underline font-medium">
          Lupa kata sandi?
        </Link>
      </div>

      <Button type="submit" size="lg" isLoading={isLoading} className="w-full">
        Masuk
      </Button>

      <div className="text-center text-sm text-slate-500 pt-2 border-t border-slate-100">
        Belum punya akun?{' '}
        <Link to="/register" className="text-primary-600 hover:underline font-medium">
          Daftar akun baru
        </Link>
      </div>
    </form>
  )
}
export default LoginPage
