import React, { createContext, useContext, useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { cmsService } from './services/cmsService'

type CmsMap = Record<string, string | null>

const CmsContext = createContext<CmsMap>({})

const CACHE_KEY = 'rafa_cms_cache'

function readCache(): CmsMap {
  if (typeof localStorage === 'undefined') return {}
  try {
    const raw = localStorage.getItem(CACHE_KEY)
    if (!raw) return {}
    const parsed = JSON.parse(raw) as CmsMap
    return parsed && typeof parsed === 'object' ? parsed : {}
  } catch {
    return {}
  }
}

function writeCache(data: CmsMap) {
  if (typeof localStorage === 'undefined') return
  try {
    localStorage.setItem(CACHE_KEY, JSON.stringify(data))
  } catch {
    /* storage unavailable — ignore */
  }
}

/**
 * Loads published landing-page content. Initial state is hydrated synchronously
 * from localStorage so the navbar logo & hero image render instantly on reload,
 * then refetched on mount/route change/tab focus. Tolerant: components rendered
 * OUTSIDE a provider read unset keys and fall back to defaults.
 */
export const CmsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [cms, setCms] = useState<CmsMap>(() => readCache())
  const location = useLocation()

  useEffect(() => {
    let mounted = true
    const load = () => {
      cmsService
        .getPublic()
        .then((data) => {
          if (mounted) {
            setCms(data)
            writeCache(data)
          }
        })
        .catch(() => undefined) // keep cached/defaults on transient failures
    }
    load()
    window.addEventListener('focus', load)
    return () => {
      mounted = false
      window.removeEventListener('focus', load)
    }
  }, [location.pathname])

  return <CmsContext.Provider value={cms}>{children}</CmsContext.Provider>
}

export const useCms = (): CmsMap => useContext(CmsContext)

export default CmsProvider