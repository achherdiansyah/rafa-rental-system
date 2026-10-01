import type { BankAccount } from './bank'

export type InvoiceStatus = 'DRAFT' | 'ISSUED' | 'UNPAID' | 'PARTIALLY_PAID' | 'PAID' | 'OVERPAID' | 'OVERDUE' | 'CANCELLED'
export type InvoiceType = 'RENTAL_PREPAYMENT' | 'DAILY_WORK' | 'MOB_DEMOB' | 'ADJUSTMENT' | 'OTHER'
export type PaymentStatus = 'PENDING' | 'SUBMITTED' | 'APPROVED' | 'REJECTED'

export interface InvoiceDetail {
  id: number
  description: string
  unit_price: number
  quantity: number
  subtotal: number
}

export interface Invoice {
  id: number
  invoice_number: string
  invoice_type: InvoiceType
  booking_id: number
  status: InvoiceStatus
  issued_at: string | null
  due_at: string | null
  subtotal: number
  tax_total: number
  grand_total: number
  paid_amount: number
  balance_amount: number
  overpayment_amount?: number
  booking?: {
    booking_code: string | null
    project: { id: number; project_name: string; city: string } | null
  }
  details: InvoiceDetail[]
  created_at: string
  updated_at: string
}

export interface PaymentProof {
  id: number
  document_type: string
  file_name: string
  mime_type: string
  file_size: number
  url: string | null
  uploaded_by: number | null
  created_at: string
}

export interface Payment {
  id: number
  invoice_id: number
  status: PaymentStatus
  amount: number
  payment_date: string
  sender_name: string | null
  reference: string | null
  rejection_reason: string | null
  proof?: PaymentProof | null
  invoice?: {
    invoice_number: string
    status: string
    booking_code: string | null
  }
  created_at: string
  updated_at: string
}

export interface BankOption {
  id: number
  bank_name: string
  account_number: string
  account_name: string
}

export const invoiceTypeLabel: Record<InvoiceType, string> = {
  RENTAL_PREPAYMENT: 'Sewa Awal',
  DAILY_WORK: 'Sewa Harian',
  MOB_DEMOB: 'MOB/DEMOB',
  ADJUSTMENT: 'Penyesuaian',
  OTHER: 'Lainnya',
}

export const canUserPay = (invoice: Invoice): boolean =>
  invoice.status === 'ISSUED' || invoice.status === 'UNPAID' || invoice.status === 'PARTIALLY_PAID' || invoice.status === 'OVERDUE'

// Re-export to keep page imports single-source.
export type { BankAccount }