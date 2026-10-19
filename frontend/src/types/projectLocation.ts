export interface ProjectLocation {
  id: number
  user_id: number
  project_name: string
  address: string
  city: string
  province_code?: string | null
  province_name?: string | null
  regency_code?: string | null
  regency_name?: string | null
  district_code?: string | null
  district_name?: string | null
  village_code?: string | null
  village_name?: string | null
  pic_name: string
  pic_phone: string
  latitude: number | null
  longitude: number | null
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CreateProjectLocationInput {
  project_name: string
  address: string
  city: string
  province_code?: string | null
  province_name?: string | null
  regency_code?: string | null
  regency_name?: string | null
  district_code?: string | null
  district_name?: string | null
  village_code?: string | null
  village_name?: string | null
  pic_name: string
  pic_phone: string
  latitude?: number | null
  longitude?: number | null
  is_active?: boolean
}

export interface UpdateProjectLocationInput extends Partial<CreateProjectLocationInput> {}

