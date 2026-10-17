import { describe, it, expect } from 'vitest'
import { targetFor, labelFor } from './utils'
import type { InAppNotification } from '@/types/notification'

const base: InAppNotification = {
  id: '1',
  type: 'SYSTEM',
  event: null,
  entity_type: null,
  entity_id: null,
  message: 'Pesan',
  link: null,
  read_at: null,
  created_at: new Date().toISOString(),
}

describe('notification role targets', () => {
  it('routes supervisor booking events to the supervisor portal', () => {
    expect(targetFor({ ...base, event: 'BOOKING_SUBMITTED' }, 'SUPERVISOR')).toBe('/supervisor/bookings')
    expect(targetFor({ ...base, event: 'BOOKING_SUPERVISOR_VALIDATED' }, 'ADMIN')).toBe('/admin/bookings')
  })

  it('routes operator assignment events to operator assignments', () => {
    expect(targetFor({ ...base, event: 'OPERATOR_ASSIGNED' }, 'OPERATOR')).toBe('/operator/assignments')
  })

  it('never falls back to the customer portal for staff roles', () => {
    expect(targetFor({ ...base, event: 'BOOKING_APPROVED' }, 'SUPERVISOR').startsWith('/supervisor')).toBe(true)
    expect(targetFor({ ...base, event: 'BOOKING_APPROVED' }, 'OPERATOR').startsWith('/operator')).toBe(true)
  })

  it('labels supervisor and operator events', () => {
    expect(labelFor({ ...base, event: 'BOOKING_SUPERVISOR_REVISION' })).toContain('Supervisor')
    expect(labelFor({ ...base, event: 'OPERATOR_ASSIGNED' })).toContain('Operator')
  })
})
