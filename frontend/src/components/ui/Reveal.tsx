import React, { useEffect, useRef, useState } from 'react'

export interface RevealProps {
  children: React.ReactNode
  delay?: number
  className?: string
}

/**
 * Lightweight scroll-reveal (IntersectionObserver + CSS transition). Elements
 * start slightly offset/transparent and animate in once visible. No library.
 * Falls back to "visible immediately" in non-browser/test environments and
 * under prefers-reduced-motion.
 */
export const Reveal: React.FC<RevealProps> = ({ children, delay = 0, className }) => {
  const ref = useRef<HTMLDivElement>(null)
  const [show, setShow] = useState(false)

  useEffect(() => {
    const el = ref.current
    if (!el) return
    if (
      typeof IntersectionObserver === 'undefined' ||
      (typeof window !== 'undefined' && window.matchMedia?.('(prefers-reduced-motion: reduce)').matches)
    ) {
      setShow(true)
      return
    }
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setShow(true)
          io.disconnect()
        }
      },
      { threshold: 0.15, rootMargin: '0px 0px -40px 0px' }
    )
    io.observe(el)
    return () => io.disconnect()
  }, [])

  return (
    <div ref={ref} style={{ transitionDelay: `${delay}ms` }} className={`reveal ${show ? 'reveal-visible' : ''} ${className ?? ''}`}>
      {children}
    </div>
  )
}
export default Reveal