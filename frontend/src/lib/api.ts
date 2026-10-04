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
  timeout: 60000, // 60s: multipart uploads + slow aggregate endpoints need headroom
})

// Request Interceptor: Attach Auth Token
apiClient.interceptors.request.use((config) => {
  const token = localStorage.getItem('rafa_token')
  if (token && config.headers) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Never set Content-Type manually for FormData: the browser (XHR/fetch) must
  // append the multipart `boundary=`. Overriding it with a bare
  // "multipart/form-data" makes PHP reject the body ("Missing boundary") and the
  // connection can be dropped before any HTTP response -> user sees "network".
  if (config.data instanceof FormData && config.headers) {
    delete config.headers['Content-Type']
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

      // Let the AuthProvider handle session cleanup + in-app navigation.
      // No full-page reload: keeps SPA state and prevents redirect loops.
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new CustomEvent('rafa:auth-expired'))
      }
    }

    // Real backend error must reach the console (dev/localhost debugging), not be
    // swallowed into a generic "no internet" message.
    if (error.response) {
      // eslint-disable-next-line no-console
      console.error('[api]', `${error.config?.method?.toUpperCase()} ${error.config?.url}`, {
        status: error.response.status,
        statusText: error.response.statusText,
        body: error.response.data,
      })
    } else {
      // eslint-disable-next-line no-console
      console.error('[api] no-response', `${error.config?.method?.toUpperCase()} ${error.config?.url}`, {
        code: error.code,
        message: error.message,
      })
    }

    return Promise.reject(normalizeApiError(error))
  }
)

/**
 * Maps staged HTTP error reasons to a user-friendly Indonesian message so the
 * real cause is shown instead of a generic "cannot connect" string.
 */
function reasonByStatus(data: ApiResponse | undefined, status: number): string {
  if (data?.message) return data.message
  switch (status) {
    case 400:
      return 'Permintaan tidak valid (400).'
    case 401:
      return 'Sesi berakhir atau token tidak valid (401). Silakan masuk kembali.'
    case 403:
      return 'Anda tidak memiliki izin untuk melakukan aksi ini (403).'
    case 404:
      return 'Data atau endpoint tidak ditemukan (404).'
    case 409:
      return data?.message || 'Data berbenturan dengan aturan bisnis (409).'
    case 413:
      return 'Ukuran berkas melebihi batas maksimum server (413).'
    case 422:
      return 'Validasi gagal (422). Periksa kembali isian Anda.'
    case 429:
      return 'Terlalu banyak permintaan (429). Coba lagi beberapa saat.'
    case 500:
    case 502:
    case 503:
    case 504:
      return 'Server sedang mengalami gangguan. Coba beberapa saat lagi.'
    default:
      return `Terjadi kesalahan pada server (${status}).`
  }
}

/**
 * Normalizes Axios Error into a consistent typed ApiError.
 */
export function normalizeApiError(error: AxiosError<ApiResponse>): ApiError {
  if (error.response) {
    // Backend responded with an error HTTP status
    const data = error.response.data
    return {
      message: reasonByStatus(data, error.response.status),
      code: data?.code || 'HTTP_ERROR',
      status: error.response.status,
      errors: (data?.errors as Record<string, string[]>) || {},
      isNetworkError: false,
    }
  } else if (error.request) {
    // Request was made but no response received (Network Error / Timeout / CORS)
    const cause =
      error.code === 'ECONNABORTED'
        ? 'Permintaan melebihi waktu tunggu server.'
        : 'Tidak dapat menerima respons dari server -- periksa konsol untuk detail penyebab.'
    return {
      message: cause,
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
