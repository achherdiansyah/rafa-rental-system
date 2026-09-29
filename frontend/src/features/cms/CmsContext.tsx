import React, { createContext, useContext, useEffect, useState } from 'react'
import { cmsService } from './services/cmsService'

type CmsMap = Record<string, string | null>

const CmsContext = createContext<CmsMap>({})

/**
 * Loads published landing-page content once. Tolerant: components rendered
 * OUTSIDE a provider (e.g. Navbar inside user/admin layouts) read unset keys
 * and fall back to defaults.
 */
export const CmsProvider: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [cms, setCms] = useState<CmsMap>({})

  useEffect(() => {
    let mounted = true
    cmsService
      .getPublic()
      .then((data) => {
        if (mounted) setCms(data)
      })
      .catch(() => undefined)
    return () => {
      mounted = false
    }
  }, [])

  return <CmsContext.Provider value={cms}>{children}</CmsContext.Provider>
}

export const useCms = (): CmsMap => useContext(CmsContext)

export default CmsProvider