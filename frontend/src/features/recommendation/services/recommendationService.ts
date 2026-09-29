import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type {
  RecommendationRequest,
  CreateRecommendationInput,
} from '@/types/recommendation'

export const recommendationService = {
  /**
   * Request equipment recommendation based on project criteria.
   */
  async requestRecommendation(
    input: CreateRecommendationInput
  ): Promise<ApiResponse<RecommendationRequest>> {
    // NOTE: axios `api` already sets baseURL = `${API_BASE}/api/v1`;
    // paths must NOT repeat the /api/v1 prefix.
    const response = await api.post<RecommendationRequest>('/recommendations/request', input)
    return response
  },

  /**
   * Get recommendation history for logged-in user.
   */
  async getHistory(
    page = 1,
    perPage = 10
  ): Promise<ApiResponse<RecommendationRequest[]>> {
    const response = await api.get<RecommendationRequest[]>('/recommendations', {
      params: { page, per_page: perPage },
    })
    return response
  },

  /**
   * Get specific recommendation details.
   */
  async getRecommendation(
    id: number
  ): Promise<ApiResponse<RecommendationRequest>> {
    const response = await api.get<RecommendationRequest>(`/recommendations/${id}`)
    return response
  },
}
