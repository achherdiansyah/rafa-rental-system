export interface RecommendationCriteria {
  id: number
  request_id: number
  project_type: string
  work_volume: number | null
  terrain_condition: string
  depth_requirement: number | null
  reach_requirement: number | null
  load_capacity: number | null
  target_productivity: string | null
  location_access: string | null
  duration_days: number | null
  budget_range: string | null
  additional_params: Record<string, any> | null
}

export interface RecommendationResult {
  id: number
  request_id: number
  equipment_model_id: number
  match_score: number
  reasoning_text: string
  model: {
    id: number
    brand: string
    model_name: string
  }
  created_at: string
}

export interface RecommendationRequest {
  id: number
  user_id: number
  status: 'PENDING' | 'PROCESSED' | 'FAILED'
  criteria: RecommendationCriteria
  results: RecommendationResult[]
  created_at: string
  updated_at: string
}

export interface CreateRecommendationInput {
  project_type: string
  terrain_condition: string
  load_capacity?: number
  work_volume?: number
  depth_requirement?: number
  reach_requirement?: number
  target_productivity?: string
  location_access?: string
  duration_days?: number
  budget_range?: string
}
