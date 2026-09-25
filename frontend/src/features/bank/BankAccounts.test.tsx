import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { AdminBankAccountsPage } from './pages/AdminBankAccountsPage'
import { bankService } from './services/bankService'
import { ToastProvider } from '@/app/ToastContext'

vi.mock('./services/bankService', () => ({
  bankService: {
    getAccounts: vi.fn(),
    getAccount: vi.fn(),
    createAccount: vi.fn(),
    updateAccount: vi.fn(),
  },
}))

const renderComponent = () => {
  return render(
    <ToastProvider>
      <AdminBankAccountsPage />
    </ToastProvider>
  )
}

describe('Admin Bank Accounts Master Page', () => {
  const mockAccounts = [
    {
      id: 1,
      bank_name: 'BCA',
      account_number: '1234567890',
      account_name: 'PT RAFA RENTAL NUSANTARA',
      is_active: true,
    },
    {
      id: 2,
      bank_name: 'Mandiri',
      account_number: '1300098765432',
      account_name: 'PT RAFA RENTAL NUSANTARA',
      is_active: false,
    },
  ]

  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(bankService.getAccounts).mockResolvedValue(mockAccounts)
  })

  it('renders page header and bank accounts table', async () => {
    renderComponent()

    expect(screen.getByRole('heading', { name: /rekening bank perusahaan/i })).toBeInTheDocument()

    await waitFor(() => {
      expect(screen.getByText('BCA')).toBeInTheDocument()
      expect(screen.getByText('1234567890')).toBeInTheDocument()
      expect(screen.getByText('Aktif (Menerima Transfer)')).toBeInTheDocument()
      expect(screen.getByText('Mandiri')).toBeInTheDocument()
      expect(screen.getByText('1300098765432')).toBeInTheDocument()
      expect(screen.getByText('Nonaktif')).toBeInTheDocument()
    })
  })

  it('opens register modal and creates a new bank account', async () => {
    vi.mocked(bankService.createAccount).mockResolvedValue({
      id: 3,
      bank_name: 'BNI',
      account_number: '9876543210',
      account_name: 'PT RAFA RENTAL NUSANTARA',
      is_active: true,
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /daftarkan rekening baru/i })).toBeInTheDocument()
    })

    fireEvent.click(screen.getByRole('button', { name: /daftarkan rekening baru/i }))

    expect(screen.getByRole('heading', { name: /daftarkan rekening bank baru/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/nama bank/i), { target: { value: 'BNI' } })
    fireEvent.change(screen.getByLabelText(/nomor rekening/i), { target: { value: '9876543210' } })

    fireEvent.click(screen.getByRole('button', { name: /^daftarkan rekening$/i }))

    await waitFor(() => {
      expect(bankService.createAccount).toHaveBeenCalledWith(expect.objectContaining({
        bank_name: 'BNI',
        account_number: '9876543210',
      }))
    })
  })

  it('opens edit modal and updates an existing bank account', async () => {
    vi.mocked(bankService.updateAccount).mockResolvedValue({
      id: 1,
      bank_name: 'BCA Prioritas',
      account_number: '1234567890',
      account_name: 'PT RAFA RENTAL NUSANTARA',
      is_active: true,
    })

    renderComponent()

    await waitFor(() => {
      expect(screen.getByLabelText(/edit rekening bca/i)).toBeInTheDocument()
    })

    fireEvent.click(screen.getByLabelText(/edit rekening bca/i))

    expect(screen.getByRole('heading', { name: /edit rekening bank perusahaan/i })).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText(/nama bank/i), { target: { value: 'BCA Prioritas' } })
    fireEvent.click(screen.getByRole('button', { name: /simpan perubahan/i }))

    await waitFor(() => {
      expect(bankService.updateAccount).toHaveBeenCalledWith(1, expect.objectContaining({
        bank_name: 'BCA Prioritas',
      }))
    })
  })
})
