import { describe, it, expect, vi, beforeEach } from 'vitest'

const { postMock, putMock, getMock, deleteMock } = vi.hoisted(() => ({
  postMock: vi.fn(),
  putMock: vi.fn(),
  getMock: vi.fn(),
  deleteMock: vi.fn(),
}))

vi.mock('@/lib/api', () => ({
  api: { post: postMock, put: putMock, get: getMock, delete: deleteMock },
}))

import { cmsService, MEDIA_KEYS } from './cmsService'

describe('cmsService', () => {
  beforeEach(() => vi.clearAllMocks())

  it('getPublic returns a flat key->value map for the landing page', async () => {
    getMock.mockResolvedValueOnce({ success: true, message: 'ok', data: { hero_title: 'Sewa Alat', brand_logo: 'http://s/storage/cms/logo.png' } })
    const out = await cmsService.getPublic()
    expect(out.hero_title).toBe('Sewa Alat')
    expect(out.brand_logo).toContain('/storage/')
  })

  it('update hits PUT /admin/cms/{key}', async () => {
    putMock.mockResolvedValueOnce({ success: true, message: 'ok', data: null })
    await cmsService.update('hero_title', 'Judul Baru')
    expect(putMock).toHaveBeenCalledWith('/admin/cms/hero_title', { value: 'Judul Baru' })
  })

  it('uploadMedia posts multipart to /admin/cms/{key}/media', async () => {
    postMock.mockResolvedValueOnce({ success: true, message: 'ok', data: { url: 'http://s/storage/cms/h.png' } })
    const file = new File(['x'], 'hero.png', { type: 'image/png' })
    const url = await cmsService.uploadMedia('hero_image', file)
    expect(postMock).toHaveBeenCalledWith('/admin/cms/hero_image/media', expect.any(FormData), expect.any(Object))
    expect(url).toContain('/storage/')
  })

  it('exposes the media key contract', () => {
    expect(MEDIA_KEYS).toEqual(['brand_logo', 'brand_favicon', 'hero_image'])
  })
})