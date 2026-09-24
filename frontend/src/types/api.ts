export interface ApiResponse<T = unknown> {
  success: boolean
  message: string
  data: T
  meta?: PaginationMeta
  errors?: Record<string, string[]> | Record<string, unknown>
  code?: string
}

export interface PaginationMeta {
  current_page: number
  per_page: number
  total: number
  last_page: number
}

export interface PaginatedResponse<T = unknown> extends ApiResponse<T[]> {
  meta: PaginationMeta
}

export interface ApiError {
  message: string
  code: string
  status: number
  errors: Record<string, string[]>
  isNetworkError: boolean
}
