import { describe, it, expect, vi } from 'vitest'
import { render, screen, fireEvent } from '@testing-library/react'
import { EmptyState } from './EmptyState'
import { ErrorState } from './ErrorState'
import { LoadingState } from './LoadingState'
import { Alert } from './Alert'

describe('Feedback components', () => {
  it('renders EmptyState with title and description', () => {
    render(<EmptyState title="Tidak Ada Data" description="Belum ada transaksi." />)
    expect(screen.getByText('Tidak Ada Data')).toBeInTheDocument()
    expect(screen.getByText('Belum ada transaksi.')).toBeInTheDocument()
  })

  it('renders ErrorState and triggers onRetry', () => {
    const onRetry = vi.fn()
    render(<ErrorState message="Gagal memuat API." onRetry={onRetry} />)
    expect(screen.getByText('Gagal memuat API.')).toBeInTheDocument()

    const retryBtn = screen.getByRole('button', { name: /try again/i })
    fireEvent.click(retryBtn)
    expect(onRetry).toHaveBeenCalledTimes(1)
  })

  it('renders LoadingState with message', () => {
    render(<LoadingState message="Memuat informasi..." />)
    expect(screen.getByText('Memuat informasi...')).toBeInTheDocument()
  })

  it('renders Alert banner with title and children', () => {
    render(<Alert variant="warning" title="Perhatian">Batas pembayaran 24 jam.</Alert>)
    expect(screen.getByText('Perhatian')).toBeInTheDocument()
    expect(screen.getByText('Batas pembayaran 24 jam.')).toBeInTheDocument()
  })
})
