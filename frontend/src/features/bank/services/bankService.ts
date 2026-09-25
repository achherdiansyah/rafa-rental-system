import { api } from '@/lib/api'
import type { BankAccount, CreateBankAccountPayload, UpdateBankAccountPayload } from '@/types/bank'

export const bankService = {
  getAccounts: async (): Promise<BankAccount[]> => {
    const response = await api.get<BankAccount[]>('/bank-accounts')
    return response.data
  },

  getAccount: async (id: number): Promise<BankAccount> => {
    const response = await api.get<BankAccount>(`/bank-accounts/${id}`)
    return response.data
  },

  createAccount: async (payload: CreateBankAccountPayload): Promise<BankAccount> => {
    const response = await api.post<BankAccount>('/bank-accounts', payload)
    return response.data
  },

  updateAccount: async (id: number, payload: UpdateBankAccountPayload): Promise<BankAccount> => {
    const response = await api.put<BankAccount>(`/bank-accounts/${id}`, payload)
    return response.data
  },
}

export default bankService
