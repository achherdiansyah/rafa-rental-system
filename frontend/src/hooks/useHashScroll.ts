import { useEffect } from 'react'
import { useLocation } from 'react-router-dom'

const prefersReducedMotion = () =>
  typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches

export function scrollToElementId(id: string, force = false) {
  if (!id) return
  const el = document.getElementById(id)
  if (!el) return
  if (prefersReducedMotion()) {
    el.scrollIntoView({ behavior: 'auto', block: 'start' })
    return
  }
  el.scrollIntoView({ behavior: force ? 'auto' : 'smooth', block: 'start' })
}

/**
 * Smooth-scrolls to `#section` on navigation. Works after refresh (initial hash)
 * and on SPA link clicks. Honors prefers-reduced-motion.
 */
export function useHashScroll() {
  const location = useLocation()

  useEffect(() => {
    const hash = location.hash.replace('#', '')
    if (!hash) return
    let raf = 0
    let timer = 0
    // wait for the target section to render on first paint / route change
    raf = requestAnimationFrame(() => scrollToElementId(hash))
    // fallback for late-layout images
    timer = window.setTimeout(() => scrollToElementId(hash), 150)
    return () => {
      cancelAnimationFrame(raf)
      clearTimeout(timer)
    }
  }, [location.pathname, location.hash])
}