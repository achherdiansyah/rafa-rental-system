export interface EquipmentType {
  id: number
  name: string
  description: string | null
  models_count?: number
  created_at?: string
  updated_at?: string
}

export interface EquipmentModel {
  id: number
  equipment_type_id: number
  brand: string
  model_name: string
  capacity_value: number
  capacity_unit: string
  is_active: boolean
  type?: EquipmentType
  units_count?: number
  created_at?: string
  updated_at?: string
}

export interface CreateEquipmentTypePayload {
  name: string
  description?: string
}

export interface UpdateEquipmentTypePayload {
  name?: string
  description?: string
}

export interface CreateEquipmentModelPayload {
  equipment_type_id: number
  brand: string
  model_name: string
  capacity_value: number
  capacity_unit: string
  is_active?: boolean
}

export interface UpdateEquipmentModelPayload {
  equipment_type_id?: number
  brand?: string
  model_name?: string
  capacity_value?: number
  capacity_unit?: string
  is_active?: boolean
}

export interface EquipmentModelFilterParams {
  search?: string
  equipment_type_id?: number | string
  brand?: string
  is_active?: boolean
  page?: number
  per_page?: number
}
