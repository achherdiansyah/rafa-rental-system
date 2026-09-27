import type { EquipmentModel } from './equipment'
import type { ProjectLocation } from './projectLocation'

export interface CartItem {
  id: number
  cart_id: number
  equipment_model_id: number
  quantity: number
  is_all_in: boolean
  start_date: string
  end_date: string
  model?: EquipmentModel
  created_at: string
  updated_at: string
}

export interface Cart {
  id: number
  user_id: number
  project_location_id: number | null
  project_location?: ProjectLocation | null
  items: CartItem[]
  created_at: string
  updated_at: string
}

export interface AddCartItemInput {
  equipment_model_id: number
  quantity: number
  is_all_in: boolean
  start_date: string
  end_date: string
  project_location_id?: number
}

export interface UpdateCartItemInput {
  quantity?: number
  is_all_in?: boolean
  start_date?: string
  end_date?: string
}