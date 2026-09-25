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
    const response = await api.post<ApiResponse<RecommendationRequest>>(
      '/api/v1/recommendations/request',
      input
    )
    return response.data
  },

  /**
   * Get recommendation history for logged-in user.
   */
  async getHistory(
    page = 1,
    perPage = 10
  ): Promise<ApiResponse<RecommendationRequest[]>> {
    const response = await api.get<ApiResponse<RecommendationRequest[]>>(
      '/api/v1/recommendations',
      {
        params: { page, per_page: perPage },
      }
    )
    return response.data
  },

  /**
   * Get specific recommendation details.
   */
  async getRecommendation(
    id: number
  ): Promise<ApiResponse<RecommendationRequest>> {
    const response = await api.get<ApiResponse<RecommendationRequest>>(
      `/api/v1/recommendations/${id}`
    )
    return response.data
  },
}
