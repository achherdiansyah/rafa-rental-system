import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { ProjectLocation, CreateProjectLocationInput, UpdateProjectLocationInput } from '@/types/projectLocation'

export const projectLocationService = {
  async getLocations(page = 1, perPage = 10, search?: string): Promise<PaginatedResponse<ProjectLocation>> {
    // api.get already resolves the envelope {success, message, data, meta};
    // returning response.data would yield the BARE ARRAY and page guards like
    // res.success/res.meta would never hold -> list stuck on empty state.
    const response = await api.get<ProjectLocation[]>('/project-locations', {
      params: { page, per_page: perPage, search },
    })
    return response as unknown as PaginatedResponse<ProjectLocation>
  },

  async getLocation(id: number): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.get<ProjectLocation>(`/project-locations/${id}`)
    return response
  },

  async createLocation(data: CreateProjectLocationInput): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.post<ProjectLocation>('/project-locations', data)
    // api.post already resolves the envelope {success, message, data}; return it
    // as-is so callers can read res.data.id (contract identical to getLocations).
    return response
  },

  async updateLocation(id: number, data: UpdateProjectLocationInput): Promise<ApiResponse<ProjectLocation>> {
    const response = await api.put<ProjectLocation>(`/project-locations/${id}`, data)
    return response
  },

  async deleteLocation(id: number): Promise<ApiResponse<null>> {
    const response = await api.delete<null>(`/project-locations/${id}`)
    return response
  },
}
