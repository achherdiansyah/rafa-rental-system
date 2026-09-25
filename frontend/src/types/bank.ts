export interface BankAccount {
  id: number
  bank_name: string
  account_number: string
  account_name: string
  is_active: boolean
  created_at?: string
  updated_at?: string
}

export interface CreateBankAccountPayload {
  bank_name: string
  account_number: string
  account_name: string
  is_active?: boolean
}

export interface UpdateBankAccountPayload {
  bank_name?: string
  account_number?: string
  account_name?: string
  is_active?: boolean
}
