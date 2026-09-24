export type EquipmentStatus =
  | 'AVAILABLE'
  | 'ASSIGNED'
  | 'MOBILIZING'
  | 'ON_SITE'
  | 'DEMOBILIZING'
  | 'RETURN_INSPECTION'
  | 'MAINTENANCE'
  | 'DECOMMISSIONED'

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

export interface EquipmentUnit {
  id: number
  equipment_model_id: number
  serial_number: string
  plate_number: string | null
  status: EquipmentStatus
  last_hour_meter: number
  year_of_make: number | null
  model?: EquipmentModel
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

export interface CreateEquipmentUnitPayload {
  equipment_model_id: number
  serial_number: string
  plate_number?: string
  status?: EquipmentStatus
  last_hour_meter?: number
  year_of_make?: number
}

export interface UpdateEquipmentUnitPayload {
  equipment_model_id?: number
  serial_number?: string
  plate_number?: string
  last_hour_meter?: number
  year_of_make?: number
}

export interface UpdateEquipmentUnitStatusPayload {
  status: EquipmentStatus
  notes?: string
}

export interface EquipmentModelFilterParams {
  search?: string
  equipment_type_id?: number | string
  brand?: string
  is_active?: boolean
  page?: number
  per_page?: number
}

export interface EquipmentUnitFilterParams {
  search?: string
  equipment_model_id?: number | string
  status?: EquipmentStatus | string
  page?: number
  per_page?: number
}
