import { describe, it, expect, vi, beforeEach } from 'vitest'

const { postMock, getMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  getMock: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  api: {
    post: postMock,
    get: getMock,
  },
}))

import { recommendationService } from './recommendationService'

const input = {
  project_type: 'Galian Basah dan Drainase',
  terrain_condition: 'Lumpur / Rawa / Basah',
  load_capacity: 20,
  work_volume: 899,
  depth_requirement: 5,
  reach_requirement: 9,
  duration_days: 14,
}

describe('recommendationService', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('posts to /recommendations/request (no duplicate /api/v1 prefix)', async () => {
    postMock.mockResolvedValueOnce({
      success: true,
      data: { id: 1 } as never,
      message: 'ok',
    } as never)

    const out = await recommendationService.requestRecommendation(input)

    expect(postMock).toHaveBeenCalledTimes(1)
    const [url, payload] = postMock.mock.calls[0] as [string, unknown]
    expect(url).toBe('/recommendations/request')
    expect(url).not.toContain('/api/v1/api/v1')
    // numeric duration must travel as a number, never as the string "hari"
    expect(payload).toMatchObject({ duration_days: 14 })
    expect(payload).not.toHaveProperty('duration_days', '14 hari')
    expect(out.data?.id).toBe(1)
  })

  it('lists history at /recommendations', async () => {
    getMock.mockResolvedValueOnce({ success: true, data: [] as never, message: 'ok' } as never)

    await recommendationService.getHistory(1, 10)

    expect(getMock).toHaveBeenCalledWith('/recommendations', { params: { page: 1, per_page: 10 } })
  })

  it('fetches detail at /recommendations/{id}', async () => {
    getMock.mockResolvedValueOnce({ success: true, data: { id: 9 } as never, message: 'ok' } as never)

    const out = await recommendationService.getRecommendation(9)

    expect(getMock).toHaveBeenCalledWith('/recommendations/9')
    expect(out.data?.id).toBe(9)
  })
})