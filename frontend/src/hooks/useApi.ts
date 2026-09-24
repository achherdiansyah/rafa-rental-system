import { useState, useCallback } from 'react'
import type { ApiError } from '@/types/api'

export interface UseApiState<T> {
  data: T | null
  error: ApiError | null
  isLoading: boolean
  isSuccess: boolean
}

export function useApi<T, P extends unknown[] = unknown[]>(
  apiFn: (...args: P) => Promise<{ data: T }>
) {
  const [state, setState] = useState<UseApiState<T>>({
    data: null,
    error: null,
    isLoading: false,
    isSuccess: false,
  })

  const execute = useCallback(
    async (...args: P): Promise<T | null> => {
      setState({ data: null, error: null, isLoading: true, isSuccess: false })
      try {
        const response = await apiFn(...args)
        setState({ data: response.data, error: null, isLoading: false, isSuccess: true })
        return response.data
      } catch (err) {
        const apiError = err as ApiError
        setState({ data: null, error: apiError, isLoading: false, isSuccess: false })
        return null
      }
    },
    [apiFn]
  )

  const reset = useCallback(() => {
    setState({ data: null, error: null, isLoading: false, isSuccess: false })
  }, [])

  return {
    ...state,
    execute,
    reset,
  }
}

export default useApi
