import React, { createContext, useContext, useEffect, useState } from 'react'
import { useLocation } from 'react-router-dom'
import { cmsService } from './services/cmsService'

type CmsMap = Record<string, string | null>

const CmsContext = createContext<CmsMap>({})

/**
 * Loads published landing-page content. Refetches on every route change and
 * when the tab regains focus so edits saved from the Admin CMS panel are
 * reflected on the public landing page. Tolerant: components rendered OUTSIDE
 * a provider read unset keys and fall back to defaults.
 */
export const CmsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [cms, setCms] = useState<CmsMap>({})
  const location = useLocation()

  useEffect(() => {
    let mounted = true
    const load = () => {
      cmsService
        .getPublic()
        .then((data) => {
          if (mounted) setCms(data)
        })
        .catch(() => undefined)
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