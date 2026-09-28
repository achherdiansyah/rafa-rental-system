import { describe, it, expect, vi, beforeEach } from 'vitest'

const { postMock } = vi.hoisted(() => ({ postMock: vi.fn() }))

vi.mock('@/lib/api', () => ({
  api: {
    post: postMock,
  },
}))

import { equipmentService } from './equipmentService'

const file = new File(['fake-image-content'], 'excavator.jpg', { type: 'image/jpeg' })

const attachment = {
  id: 42,
  document_type: 'EQUIPMENT_PHOTO',
  file_name: 'excavator.jpg',
  mime_type: 'image/jpeg',
  file_size: 1024,
  url: 'http://127.0.0.1:8000/storage/equipment/2026/09/x.jpg',
  uploaded_by: 1,
}

describe('equipmentService.uploadModelPhoto', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('returns the attachment from the envelope {success, message, data}', async () => {
    postMock.mockResolvedValueOnce({ success: true, message: 'ok', data: attachment })

    const result = await equipmentService.uploadModelPhoto(7, file)

    expect(result).toEqual(attachment)
    expect(postMock).toHaveBeenCalledTimes(1)
    expect(postMock.mock.calls[0][0]).toBe('/equipment/models/7/photos')
    expect(postMock.mock.calls[0][1] instanceof FormData).toBe(true)
  })

  it('throws when the envelope has no data (upload must not be assumed saved)', async () => {
    postMock.mockResolvedValueOnce({ success: true, message: 'ok', data: null })

    await expect(equipmentService.uploadModelPhoto(7, file)).rejects.toThrow(
      'Upload berhasil tetapi respons tidak valid.'
    )
  })
})