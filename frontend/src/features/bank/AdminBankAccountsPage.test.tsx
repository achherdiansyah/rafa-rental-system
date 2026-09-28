import { describe, it, expect, vi, beforeEach } from 'vitest'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { ToastProvider } from '@/app/ToastContext'
import { AdminBankAccountsPage } from './pages/AdminBankAccountsPage'
import { bankService } from './services/bankService'
import type { BankAccount } from '@/types/bank'

vi.mock('./services/bankService', () => ({
  bankService: {
    getAccounts: vi.fn(),
    getAccount: vi.fn(),
    createAccount: vi.fn(),
    updateAccount: vi.fn(),
    deleteAccount: vi.fn(),
  },
}))

const accounts: BankAccount[] = [
  {
    id: 1,
    bank_name: 'BCA',
    account_number: '1111111111',
    account_name: 'PT RAFA RENTAL NUSANTARA',
    is_active: true,
  },
  {
    id: 2,
    bank_name: 'Mandiri',
    account_number: '2222222222',
    account_name: 'PT RAFA RENTAL NUSANTARA',
    is_active: false,
  },
]

const renderComponent = () =>
  render(
    <ToastProvider>
      <AdminBankAccountsPage />
    </ToastProvider>
  )

describe('Admin Bank Accounts UI', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(bankService.getAccounts).mockResolvedValue(accounts)
  })

  it('fetches the list exactly once on mount (no duplicate request)', async () => {
    renderComponent()

    expect(await screen.findByText('BCA')).toBeInTheDocument()
    expect(bankService.getAccounts).toHaveBeenCalledTimes(1)
  })

  it('renders empty state distinctly from data state', async () => {
    vi.mocked(bankService.getAccounts).mockResolvedValueOnce([])

    renderComponent()

    expect(await screen.findByText('Belum Ada Rekening Bank')).toBeInTheDocument()
    expect(screen.queryByText('BCA')).not.toBeInTheDocument()
  })

  it('renders error state (distinct from empty) when fetch fails', async () => {
    vi.mocked(bankService.getAccounts).mockRejectedValueOnce(new Error('network down'))

    renderComponent()

    expect(await screen.findByText('Gagal memuat data')).toBeInTheDocument()
    expect(screen.queryByText('Belum Ada Rekening Bank')).not.toBeInTheDocument()
  })

  it('cancels delete without calling the API', async () => {
    renderComponent()
    await screen.findByText('BCA')

    fireEvent.click(screen.getByLabelText('Hapus rekening BCA'))
    fireEvent.click(screen.getByText('Batal'))

    expect(bankService.deleteAccount).not.toHaveBeenCalled()
    expect(bankService.getAccounts).toHaveBeenCalledTimes(1)
  })

  it('deletes an account after confirmation and refreshes the list', async () => {
    vi.mocked(bankService.deleteAccount).mockResolvedValueOnce(undefined)
    renderComponent()
    await screen.findByText('BCA')

    fireEvent.click(screen.getByLabelText('Hapus rekening BCA'))
    fireEvent.click(screen.getByText('Hapus Rekening'))

    await waitFor(() => expect(bankService.deleteAccount).toHaveBeenCalledWith(1))
    // success toast + refetch
    await waitFor(() => expect(bankService.getAccounts).toHaveBeenCalledTimes(2))
  })

  it('surfaces the business-rule message when account is in use', async () => {
    vi.mocked(bankService.deleteAccount).mockRejectedValueOnce({
      message: 'Rekening tidak dapat dihapus karena sudah dipakai 1 transaksi pembayaran.',
      code: 'BUSINESS_RULE_VIOLATION',
      status: 409,
      errors: {},
      isNetworkError: false,
    })

    renderComponent()
    await screen.findByText('BCA')

    fireEvent.click(screen.getByLabelText('Hapus rekening BCA'))
    fireEvent.click(screen.getByText('Hapus Rekening'))

    expect(
      await screen.findByText('Rekening tidak dapat dihapus karena sudah dipakai 1 transaksi pembayaran.')
    ).toBeInTheDocument()
    // no refresh after a failed delete
    expect(bankService.getAccounts).toHaveBeenCalledTimes(1)
  })
})