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

export interface EquipmentAttachment {
  id: number
  document_type: string
  file_name: string
  mime_type: string
  file_size: number
  url: string
  uploaded_by: number
  created_at?: string
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
  attachments?: EquipmentAttachment[]
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

export interface EquipmentPriceVersion {
  id: number
  equipment_price_id: number
  old_base_rate: number
  new_base_rate: number
  changed_at: string
  changed_by: number
  changed_by_user?: {
    id: number
    name: string
  }
}

export interface EquipmentPrice {
  id: number
  equipment_model_id: number
  price_type: 'HOURLY' | 'DAILY' | 'MONTHLY' | 'LUMP_SUM'
  is_all_in: boolean
  base_rate: number
  minimum_hours: number
  overtime_rate: number
  effective_date: string
  model?: EquipmentModel
  versions?: EquipmentPriceVersion[]
  created_at?: string
  updated_at?: string
}

export interface CreateEquipmentPricePayload {
  equipment_model_id: number
  price_type: 'HOURLY' | 'DAILY' | 'MONTHLY' | 'LUMP_SUM'
  is_all_in: boolean
  base_rate: number
  minimum_hours: number
  overtime_rate: number
  effective_date: string
}

export interface UpdateEquipmentPricePayload {
  base_rate: number
  minimum_hours?: number
  overtime_rate?: number
  effective_date?: string
}

export interface EquipmentPriceFilterParams {
  equipment_model_id?: number | string
  is_all_in?: boolean
  page?: number
  per_page?: number
}
