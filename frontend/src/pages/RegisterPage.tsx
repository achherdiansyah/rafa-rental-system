import React, { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useAuth } from '@/hooks/useAuth'
import { Input } from '@/components/form/Input'
import { PasswordInput } from '@/components/form/PasswordInput'
import { Button } from '@/components/ui/Button'
import { Alert } from '@/components/feedback/Alert'
import type { ApiError } from '@/types/api'

export const RegisterPage: React.FC = () => {
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [passwordConfirm, setPasswordConfirm] = useState('')
  const [phone, setPhone] = useState('')
  const [companyName, setCompanyName] = useState('')
  const [identityNumber, setIdentityNumber] = useState('')

  const [error, setError] = useState<string | null>(null)
  const [validationErrors, setValidationErrors] = useState<Record<string, string[]>>({})
  const [isLoading, setIsLoading] = useState(false)

  const { register } = useAuth()
  const navigate = useNavigate()

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault()
    setError(null)
    setValidationErrors({})

    if (password !== passwordConfirm) {
      setError('Konfirmasi kata sandi tidak cocok.')
      return
    }

    if (password.length < 8) {
      setError('Kata sandi harus minimal 8 karakter.')
      return
    }

    setIsLoading(true)

    try {
      await register({
        name,
        email,
        password,
        password_confirmation: passwordConfirm,
        phone_number: phone,
        company_name: companyName,
        identity_type: 'KTP',
        identity_number: identityNumber,
      })

      navigate('/app')
    } catch (err) {
      const apiError = err as ApiError
      if (apiError.status === 422) {
        setValidationErrors(apiError.errors)
        setError('Terdapat kesalahan pada data formulir Anda.')
      } else {
        setError(apiError.message || 'Pendaftaran gagal. Silakan coba kembali.')
      }
    } finally {
      setIsLoading(false)
    }
  }

  const getFieldError = (field: string) => {
    return validationErrors[field]?.[0]
  }

  return (
    <form onSubmit={handleSubmit} className="space-y-4">
      <div>
        <h3 className="text-xl font-bold text-slate-900">Daftar Akun Baru</h3>
        <p className="text-sm text-slate-500 mt-1">Lengkapi data untuk memulai penyewaan armada</p>
      </div>

      {error && <Alert variant="danger">{error}</Alert>}

      <Input
        label="Nama Lengkap / PIC"
        required
        value={name}
        onChange={(e) => setName(e.target.value)}
        error={getFieldError('name')}
        placeholder="Budi Santoso"
      />

      <Input
        label="Email"
        type="email"
        required
        value={email}
        onChange={(e) => setEmail(e.target.value)}
        error={getFieldError('email')}
        placeholder="budi@perusahaan.com"
      />

      <Input
        label="Nomor Telepon / WhatsApp"
        type="tel"
        required
        value={phone}
        onChange={(e) => setPhone(e.target.value)}
        error={getFieldError('phone_number')}
        placeholder="081234567890"
      />

      <Input
        label="Nama Perusahaan (Bila Berbadan Usaha)"
        value={companyName}
        onChange={(e) => setCompanyName(e.target.value)}
        error={getFieldError('company_name')}
        placeholder="PT Maju Konstruksi Jaya"
      />

      <Input
        label="Nomor KTP / NIK"
        required
        value={identityNumber}
        onChange={(e) => setIdentityNumber(e.target.value)}
        error={getFieldError('identity_number')}
        placeholder="3201012345670001"
      />

      <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <PasswordInput
          label="Kata Sandi"
          required
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          error={getFieldError('password')}
          placeholder="Minimal 8 karakter"
        />

        <PasswordInput
          label="Ulangi Kata Sandi"
          required
          value={passwordConfirm}
          onChange={(e) => setPasswordConfirm(e.target.value)}
          placeholder="Konfirmasi kata sandi"
        />
      </div>

      <Button type="submit" size="lg" isLoading={isLoading} className="w-full mt-2">
        Daftar Sekarang
      </Button>

      <div className="text-center text-sm text-slate-500 pt-2 border-t border-slate-100">
        Sudah memiliki akun?{' '}
        <Link to="/login" className="text-primary-600 hover:underline font-medium">
          Masuk di sini
        </Link>
      </div>
    </form>
  )
}
export default RegisterPage
