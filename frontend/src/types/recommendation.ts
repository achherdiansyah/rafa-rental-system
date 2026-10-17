import type { EquipmentModel } from './equipment'
import type { ProjectLocation } from './projectLocation'

export interface RecommendationBreakdownItem {
  name: string
  code: string
  weight: number
  score: number
  contribution: number
}

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
  start_date: string | null
  end_date: string | null
  budget_range: string | null
  additional_params: Record<string, any> | null
}

export interface RecommendationResult {
  id: number
  request_id: number
  equipment_model_id: number
  rank?: number
  match_score: number
  score_breakdown: Record<string, RecommendationBreakdownItem> | null
  available_units: number | null
  reasoning_text: string
  model?: EquipmentModel
  created_at: string
}

export interface RecommendationRequest {
  id: number
  user_id: number
  status: 'PENDING' | 'PROCESSED' | 'FAILED'
  project_location_id: number | null
  project_location?: ProjectLocation | null
  criteria: RecommendationCriteria
  results: RecommendationResult[]
  created_at: string
  updated_at: string
}

export interface CreateRecommendationInput {
  project_type: string
  terrain_condition: string
  project_location_id?: number | null
  load_capacity?: number
  work_volume?: number
  depth_requirement?: number
  reach_requirement?: number
  target_productivity?: string
  location_access?: string
  duration_days?: number
  start_date?: string
  end_date?: string
  budget_range?: string
}
