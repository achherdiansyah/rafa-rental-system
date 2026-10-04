import { describe, it, expect, vi, beforeEach } from 'vitest'
import apiClient, { normalizeApiError } from './api'
import type { AxiosError } from 'axios'

describe('api client multipart handling', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    localStorage.clear()
  })

  it('request interceptor strips manual Content-Type for FormData so boundary is set by browser', () => {
    // Grab the registered request interceptor handler
    const handlers = (apiClient.interceptors.request as any).handlers
    const requestInterceptor = handlers[handlers.length - 1].fulfilled

    const formData = new FormData()
    formData.append('photo', new File(['x'], 'photo.jpg', { type: 'image/jpeg' }))

    const config = {
      url: '/equipment/models/1/photos',
      method: 'post',
      headers: { 'Content-Type': 'multipart/form-data' },
      data: formData,
    }

    const out = requestInterceptor(config)
    expect(out.headers['Content-Type']).toBeUndefined()
  })

  it('keeps Content-Type application/json for non-FormData requests', () => {
    const handlers = (apiClient.interceptors.request as any).handlers
    const requestInterceptor = handlers[handlers.length - 1].fulfilled

    const config = {
      url: '/bookings',
      method: 'post',
      headers: { 'Content-Type': 'application/json' },
      data: { booking: true },
    }

    const out = requestInterceptor(config)
    expect(out.headers['Content-Type']).toBe('application/json')
  })

  it('normalizes a 422 to its real backend message (not generic network)', async () => {
    const error = {
      response: {
        status: 422,
        data: { success: false, message: 'The photo field is required.', errors: { photo: ['required'] } },
      },
    } as unknown as AxiosError<any>

    const normalized = normalizeApiError(error)
    expect(normalized.status).toBe(422)
    expect(normalized.message).toBe('The photo field is required.')
    expect(normalized.isNetworkError).toBe(false)
  })

  it('maps 403 to a clear permission message', async () => {
    const error = {
      response: {
        status: 403,
        data: { success: false, message: '', errors: {} },
      },
    } as unknown as AxiosError<any>

    const normalized = normalizeApiError(error)
    expect(normalized.status).toBe(403)
    expect(normalized.message).toContain('tidak memiliki izin')
  })
})