export interface ProjectLocation {
  id: number
  user_id: number
  project_name: string
  address: string
  city: string
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
  pic_name: string
  pic_phone: string
  latitude?: number
  longitude?: number
  is_active?: boolean
}

export interface UpdateProjectLocationInput extends Partial<CreateProjectLocationInput> {}
