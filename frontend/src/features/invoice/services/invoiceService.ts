import { api } from '@/lib/api'
import apiClient from '@/lib/api'
import type { Invoice, Payment } from '@/types/invoice'

export interface SubmitPaymentPayload {
  amount: number
  payment_date: string
  bank_account_id: number
  sender_name?: string
  reference?: string
  proof: File
}

export const invoiceService = {
  getInvoices: async (params?: { status?: string; booking_id?: number; page?: number; per_page?: number }): Promise<{
    data: Invoice[]
    meta: { total: number; current_page: number; per_page: number; last_page: number }
  }> => {
    const response = await api.get<Invoice[]>('/invoices', { params })
    return { data: response.data ?? [], meta: response.meta ?? { total: 0, current_page: 1, per_page: 10, last_page: 1 } }
  },

  getInvoice: async (id: number): Promise<Invoice> => {
    const response = await api.get<Invoice>(`/invoices/${id}`)
    return response.data
  },

  getPayments: async (invoiceId: number): Promise<Payment[]> => {
    const response = await api.get<Payment[]>(`/invoices/${invoiceId}/payments`)
    return response.data ?? []
  },

  submitPayment: async (invoiceId: number, payload: SubmitPaymentPayload): Promise<Payment> => {
    const formData = new FormData()
    formData.append('amount', String(payload.amount))
    formData.append('payment_date', payload.payment_date)
    formData.append('bank_account_id', String(payload.bank_account_id))
    if (payload.sender_name) formData.append('sender_name', payload.sender_name)
    if (payload.reference) formData.append('reference', payload.reference)
    formData.append('proof', payload.proof)

    const response = await api.post<Payment>(`/invoices/${invoiceId}/payments`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  },

  approvePayment: async (paymentId: number): Promise<Payment> => {
    const response = await api.post<Payment>(`/payments/${paymentId}/approve`, {})
    return response.data
  },

  rejectPayment: async (paymentId: number, reason: string): Promise<Payment> => {
    const response = await api.post<Payment, { reason: string }>(`/payments/${paymentId}/reject`, { reason })
    return response.data
  },

  queuePayments: async (params?: { status?: string; page?: number; per_page?: number }): Promise<{
    data: Payment[]
    meta: { total: number; current_page: number; per_page: number; last_page: number }
  }> => {
    const response = await api.get<Payment[]>('/payments', { params })
    return { data: response.data ?? [], meta: response.meta ?? { total: 0, current_page: 1, per_page: 10, last_page: 1 } }
  },

  extendDeadline: async (invoiceId: number, hours: number): Promise<Invoice> => {
    const response = await api.post<Invoice, { hours: number }>(`/invoices/${invoiceId}/extend-deadline`, { hours })
    return response.data
  },

  proofUrl: (paymentId: number): string => `${import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1'}/payments/${paymentId}/proof`,

  async fetchProofObjectUrl(paymentId: number): Promise<string> {
    const response = await apiClient.get(`/payments/${paymentId}/proof`, { responseType: 'blob' })
    return URL.createObjectURL(response.data)
  },
}