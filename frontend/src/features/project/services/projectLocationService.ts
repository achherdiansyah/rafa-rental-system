import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { ProjectLocation, CreateProjectLocationInput, UpdateProjectLocationInput } from '@/types/projectLocation'

export const projectLocationService = {
  async getLocations(page = 1, perPage = 10, search?: string): Promise<PaginatedResponse<ProjectLocation>> {
    const response = await api.get<ProjectLocation[]>('/project-locations', {
      params: { page, per_page: perPage, search },
    })
    return response.data as unknown as PaginatedResponse<ProjectLocation>
  },

  async getLocation(id: number): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.get<ProjectLocation>(`/project-locations/${id}`)
    return response.data as unknown as ApiResponse<ProjectLocation>
  },

  async createLocation(data: CreateProjectLocationInput): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.post<ProjectLocation>('/project-locations', data)
    return response.data as unknown as ApiResponse<ProjectLocation>
  },

  async updateLocation(id: number, data: UpdateProjectLocationInput): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.put<ProjectLocation>(`/project-locations/${id}`, data)
    return response.data as unknown as ApiResponse<ProjectLocation>
  },

  async deleteLocation(id: number): Promise<ApiResponse<null>> {
    const response = await api.delete<null>(`/project-locations/${id}`)
    return response.data as unknown as ApiResponse<null>
  },
}
