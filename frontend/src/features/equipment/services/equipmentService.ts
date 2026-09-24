import { api } from '@/lib/api'
import type { PaginatedResponse, ApiResponse } from '@/types/api'
import type {
  EquipmentType,
  EquipmentModel,
  CreateEquipmentTypePayload,
  UpdateEquipmentTypePayload,
  CreateEquipmentModelPayload,
  UpdateEquipmentModelPayload,
  EquipmentModelFilterParams,
} from '@/types/equipment'

export const equipmentService = {
  // --- Equipment Types ---
  getTypes: async (search?: string, all: boolean = false, page: number = 1): Promise<PaginatedResponse<EquipmentType> | ApiResponse<EquipmentType[]>> => {
    const params = new URLSearchParams()
    if (search) params.append('search', search)
    if (all) params.append('all', '1')
    if (page) params.append('page', String(page))

    const response = await api.get<EquipmentType[]>(`/equipment/types?${params.toString()}`)
    return response as unknown as PaginatedResponse<EquipmentType> // Overloaded logic in controller
  },

  createType: async (payload: CreateEquipmentTypePayload): Promise<EquipmentType> => {
    const response = await api.post<EquipmentType>('/equipment/types', payload)
    return response.data
  },

  updateType: async (id: number, payload: UpdateEquipmentTypePayload): Promise<EquipmentType> => {
    const response = await api.put<EquipmentType>(`/equipment/types/${id}`, payload)
    return response.data
  },

  deleteType: async (id: number): Promise<void> => {
    await api.delete(`/equipment/types/${id}`)
  },

  // --- Equipment Models ---
  getModels: async (params: EquipmentModelFilterParams = {}): Promise<PaginatedResponse<EquipmentModel>> => {
    const query = new URLSearchParams()
    if (params.search) query.append('search', params.search)
    if (params.equipment_type_id) query.append('equipment_type_id', String(params.equipment_type_id))
    if (params.brand) query.append('brand', params.brand)
    if (params.is_active !== undefined) query.append('is_active', params.is_active ? '1' : '0')
    if (params.page) query.append('page', String(params.page))
    if (params.per_page) query.append('per_page', String(params.per_page))

    return api.getPaginated<EquipmentModel>(`/equipment/models?${query.toString()}`)
  },

  createModel: async (payload: CreateEquipmentModelPayload): Promise<EquipmentModel> => {
    const response = await api.post<EquipmentModel>('/equipment/models', payload)
    return response.data
  },

  updateModel: async (id: number, payload: UpdateEquipmentModelPayload): Promise<EquipmentModel> => {
    const response = await api.put<EquipmentModel>(`/equipment/models/${id}`, payload)
    return response.data
  },

  deleteModel: async (id: number): Promise<void> => {
    await api.delete(`/equipment/models/${id}`)
  },
}

export default equipmentService
