export type RefundStatus = 'PENDING' | 'APPROVED' | 'PROCESSING' | 'COMPLETED' | 'FAILED'
export type RefundSource = 'CANCELLATION' | 'OVERPAYMENT'

export interface Refund {
  id: number
  invoice_id: number
  source: RefundSource
  amount: number
  reason: string
  approval_reason: string | null
  customer_bank_info: string | null
  transfer_reference: string | null
  status: RefundStatus
  failure_reason: string | null
  approved_at: string | null
  processed_at: string | null
  completed_at: string | null
  invoice?: {
    invoice_number: string
    booking_code: string | null
    project: { project_name: string; city: string } | null
  }
  created_at: string
}

export interface OutstandingLine {
  id: number
  invoice_number: string
  status: string
  grand_total: number
  paid_amount: number
  balance_amount: number
}

export interface CustomerOutstanding {
  user_id: number
  customer_name: string | null
  total_outstanding: number
  open_invoice_count: number
  overdue_invoice_count: number
  eligible: boolean
  invoices: OutstandingLine[]
}

export const REFUND_STEPS: RefundStatus[] = ['PENDING', 'APPROVED', 'PROCESSING', 'COMPLETED']