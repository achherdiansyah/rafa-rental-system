import { api } from '@/lib/api'
import type { CustomerOutstanding, Refund } from '@/types/refund'

export const refundService = {
  getRefunds: async (params?: { status?: string; page?: number; per_page?: number }): Promise<{
    data: Refund[]
    meta: { total: number; current_page: number; per_page: number; last_page: number }
  }> => {
    const response = await api.get<Refund[]>('/refunds', { params })
    return { data: response.data ?? [], meta: response.meta ?? { total: 0, current_page: 1, per_page: 15, last_page: 1 } }
  },

  approve: async (id: number, approvalReason?: string): Promise<Refund> => {
    const response = await api.post<Refund, { approval_reason?: string }>(`/refunds/${id}/approve`, { approval_reason: approvalReason })
    return response.data
  },

  process: async (id: number, payload: { customer_bank_info: string; transfer_reference?: string }): Promise<Refund> => {
    const response = await api.post<Refund>('/refunds/' + id + '/process', payload)
    return response.data
  },

  complete: async (id: number, payload: { transfer_reference?: string; proof: File }): Promise<Refund> => {
    const formData = new FormData()
    if (payload.transfer_reference) formData.append('transfer_reference', payload.transfer_reference)
    formData.append('proof', payload.proof)

    const response = await api.post<Refund>(`/refunds/${id}/complete`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data
  },

  fail: async (id: number, failureReason: string): Promise<Refund> => {
    const response = await api.post<Refund, { failure_reason: string }>(`/refunds/${id}/fail`, { failure_reason: failureReason })
    return response.data
  },
}

export const financeService = {
  myOutstanding: async (): Promise<CustomerOutstanding> => {
    const response = await api.get<CustomerOutstanding>('/finance/outstanding/me')
    return response.data
  },

  allOutstanding: async (): Promise<CustomerOutstanding[]> => {
    const response = await api.get<CustomerOutstanding[]>('/finance/outstanding')
    return response.data ?? []
  },
}