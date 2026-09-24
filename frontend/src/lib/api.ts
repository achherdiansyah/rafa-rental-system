import axios, { AxiosError } from 'axios'
import type { AxiosRequestConfig, AxiosResponse } from 'axios'
import type { ApiError, ApiResponse, PaginatedResponse } from '@/types/api'

export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://127.0.0.1:8000/api/v1'

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 10000, // 10 seconds timeout
})

// Request Interceptor: Attach Auth Token
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('rafa_token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response Interceptor: Error Normalization & Global Auth Handling
apiClient.interceptors.response.use(
  (response: AxiosResponse) => response,
  (error: AxiosError<ApiResponse>) => {
    // Check if unauthorized
    if (error.response?.status === 401) {
      localStorage.removeItem('rafa_token')
      localStorage.removeItem('rafa_user')

      // Auto redirect to login if not already there, preventing infinite loops
      if (typeof window !== 'undefined' && !window.location.pathname.includes('/login')) {
        window.location.href = '/login?expired=1'
      }
    }

    return Promise.reject(normalizeApiError(error))
  }
)

/**
 * Normalizes Axios Error into a consistent typed ApiError.
 */
function normalizeApiError(error: AxiosError<ApiResponse>): ApiError {
  if (error.response) {
    // Backend responded with an error HTTP status
    const data = error.response.data
    return {
      message: data?.message || 'Terjadi kesalahan pada server.',
      code: data?.code || 'HTTP_ERROR',
      status: error.response.status,
      errors: (data?.errors as Record<string, string[]>) || {},
      isNetworkError: false,
    }
  } else if (error.request) {
    // Request was made but no response received (Network Error / Timeout)
    return {
      message: 'Tidak dapat terhubung ke server. Periksa koneksi internet Anda.',
      code: 'NETWORK_ERROR',
      status: 0,
      errors: {},
      isNetworkError: true,
    }
  }

  // Fallback
  return {
    message: error.message || 'Terjadi kesalahan yang tidak diketahui.',
    code: 'UNKNOWN_ERROR',
    status: 0,
    errors: {},
    isNetworkError: false,
  }
}

/**
 * Reusable HTTP Request Abstractions
 */
export const api = {
  get: async <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const response = await apiClient.get<ApiResponse<T>>(url, config)
    return response.data
  },

  getPaginated: async <T>(url: string, config?: AxiosRequestConfig): Promise<PaginatedResponse<T>> => {
    const response = await apiClient.get<PaginatedResponse<T>>(url, config)
    return response.data
  },

  post: async <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const response = await apiClient.post<ApiResponse<T>>(url, data, config)
    return response.data
  },

  put: async <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const response = await apiClient.put<ApiResponse<T>>(url, data, config)
    return response.data
  },

  patch: async <T, D = unknown>(url: string, data?: D, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const response = await apiClient.patch<ApiResponse<T>>(url, data, config)
    return response.data
  },

  delete: async <T>(url: string, config?: AxiosRequestConfig): Promise<ApiResponse<T>> => {
    const response = await apiClient.delete<ApiResponse<T>>(url, config)
    return response.data
  },
}

export default apiClient
