import { api } from '@/lib/api'

export interface CmsRecord {
  key: string
  value: string | null
  url: string | null
  is_media: boolean
  is_active: boolean
  updated_at?: string
}

export const MEDIA_KEYS = ['brand_logo', 'brand_favicon', 'hero_image']

export const cmsService = {
  async getPublic(): Promise<Record<string, string | null>> {
    const response = await api.get<Record<string, string | null>>('/cms/public')
    return response.data ?? {}
  },

  async listAdmin(): Promise<CmsRecord[]> {
    const response = await api.get<CmsRecord[]>('/admin/cms')
    return response.data ?? []
  },

  async update(key: string, value: string): Promise<void> {
    await api.put<unknown, { value: string }>(`/admin/cms/${key}`, { value })
  },

  async uploadMedia(key: string, file: File): Promise<string> {
    const { compressImage } = await import('@/utils/image')
    const prepared = await compressImage(file)
    const formData = new FormData()
    formData.append('media', prepared)
    const response = await api.post<{ url: string }>(`/admin/cms/${key}/media`, formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    return response.data?.url ?? ''
  },

  async disable(key: string): Promise<void> {
    await api.delete(`/admin/cms/${key}`)
  },
}