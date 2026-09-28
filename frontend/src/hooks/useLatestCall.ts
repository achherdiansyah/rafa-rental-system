import { useCallback, useEffect, useRef } from 'react'

/**
 * Guards async loading helpers against stale/raced requests.
 *
 * Every `run()` bumps a sequence number. Results (resolve or reject) that
 * arrive after a NEWER request has started — or after unmount — are silently
 * ignored. Only the LATEST request may surface its (real) error, so a slow
 * in-flight call can never trigger a false "gagal memuat" when the newer call
 * succeeded.
 */
export function useLatestCall(): {
  run: <T>(task: () => Promise<T>) => Promise<T | null>
} {
  const seqRef = useRef(0)
  const mountedRef = useRef(true)

  useEffect(() => {
    const seq = seqRef
    const mounted = mountedRef

    // Re-assert mounted on EVERY effect run. StrictMode double-mounts in dev
    // (mount#1 → cleanup → mount#2); without this reset, mount#1's cleanup
    // leaves mounted=false and every later run() would return null forever,
    // so stale-skip branches would never clear isLoading → stuck skeleton.
    mounted.current = true

    return () => {
      mounted.current = false
      seq.current++ // invalidate any in-flight tail on unmount
    }
  }, [])

  const run = useCallback(async <T>(task: () => Promise<T>): Promise<T | null> => {
    const seq = ++seqRef.current
    try {
      const result = await task()
      if (seq !== seqRef.current || !mountedRef.current) {
        return null // stale: a newer call owns the state now
      }
      return result
    } catch (error) {
      if (seq !== seqRef.current || !mountedRef.current) {
        return null // stale failure — never show error for outdated requests
      }
      throw error // real failure of the latest request; let the caller decide
    }
  }, [])

  return { run }
}