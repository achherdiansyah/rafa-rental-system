import { describe, it, expect, vi, afterEach } from 'vitest'
import {
  jakartaToday,
  minRentalStartDate,
  isRentalStartValid,
  isRentalEndValid,
} from './rentalDates'

describe('rentalDates H-1 rules (Asia/Jakarta)', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('computes today and the minimum start date in Jakarta time', () => {
    // 2026-10-20 09:00 Asia/Jakarta
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T02:00:00Z'))

    expect(jakartaToday()).toBe('2026-10-20')
    expect(minRentalStartDate()).toBe('2026-10-21')
  })

  it('rolls the window at Jakarta midnight, not UTC midnight', () => {
    // 2026-10-21 01:00 Asia/Jakarta (still 2026-10-20 UTC)
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T18:00:00Z'))

    expect(jakartaToday()).toBe('2026-10-21')
    expect(minRentalStartDate()).toBe('2026-10-22')
  })

  it('rejects yesterday and today, accepts tomorrow or later', () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-10-20T02:00:00Z'))

    expect(isRentalStartValid('2026-10-19')).toBe(false)
    expect(isRentalStartValid('2026-10-20')).toBe(false)
    expect(isRentalStartValid('2026-10-21')).toBe(true)
    expect(isRentalStartValid('2026-11-01')).toBe(true)
  })

  it('rejects an end date before the start date', () => {
    expect(isRentalEndValid('2026-10-21', '2026-10-20')).toBe(false)
    expect(isRentalEndValid('2026-10-21', '2026-10-21')).toBe(true)
    expect(isRentalEndValid('2026-10-21', '2026-10-25')).toBe(true)
  })
})
