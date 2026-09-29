import { describe, it, expect, vi, beforeEach } from 'vitest'

const { postMock, putMock, getMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  putMock: vi.fn(),
  getMock: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  api: {
    post: postMock,
    put: putMock,
    get: getMock,
    delete: vi.fn(),
  },
}))

import { projectLocationService } from './projectLocationService'
import type { ProjectLocation } from '@/types/projectLocation'

const location: ProjectLocation = {
  id: 21,
  user_id: 7,
  project_name: 'Tenggilis',
  address: 'Rungkut Asri',
  city: 'Surabaya',
  pic_name: 'Wahyu Setiawan',
  pic_phone: '0897789012',
  latitude: null,
  longitude: null,
  is_active: true,
  created_at: '2026-09-28T00:00:00Z',
  updated_at: '2026-09-28T00:00:00Z',
}

describe('projectLocationService create/update contract', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('createLocation returns the envelope {success, message, data} so res.data.id is valid', async () => {
    postMock.mockResolvedValueOnce({ success: true, message: 'ok', data: location })

    const res = await projectLocationService.createLocation({
      project_name: 'Tenggilis',
      address: 'Rungkut Asri',
      city: 'Surabaya',
      pic_name: 'Wahyu Setiawan',
      pic_phone: '0897789012',
    })

    // The exact guard the page uses — must NOT trip after a valid create
    expect(res?.data?.id).toBe(21)
    expect(res.data?.project_name).toBe('Tenggilis')
  })

  it('updateLocation returns the envelope so res.data.id is valid', async () => {
    putMock.mockResolvedValueOnce({ success: true, message: 'ok', data: { ...location, city: 'Sidoarjo' } })

    const res = await projectLocationService.updateLocation(21, { city: 'Sidoarjo' })

    expect(res?.data?.id).toBe(21)
    expect(res.data?.city).toBe('Sidoarjo')
  })

  it('getLocations returns the envelope (not the bare array) so res.success/res.data/res.meta hold', async () => {
    getMock.mockResolvedValue({
      success: true,
      message: 'ok',
      data: [location],
      meta: { current_page: 1, per_page: 10, total: 1, last_page: 1 },
    })

    const res = await projectLocationService.getLocations(1, 10, '')

    // The EXACT guards the page uses — without these the list would render empty
    expect(res.success).toBe(true)
    expect(Array.isArray(res.data)).toBe(true)
    expect(res.data?.[0]?.id).toBe(21)
    expect(res.meta?.total).toBe(1)
  })
})