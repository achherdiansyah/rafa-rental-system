import { api } from '@/lib/api'
import type { InAppNotification, NotificationDelivery } from '@/types/notification'

export const notificationService = {
  getNotifications: async (params?: { read?: boolean; per_page?: number }): Promise<{
    data: InAppNotification[]
    meta: { total: number; current_page: number; per_page: number; last_page: number }
  }> => {
    const response = await api.get<InAppNotification[]>('/notifications', { params })
    return { data: response.data ?? [], meta: response.meta ?? { total: 0, current_page: 1, per_page: 15, last_page: 1 } }
  },

  unreadCount: async (): Promise<number> => {
    const response = await api.get<{ count: number }>('/notifications/unread-count')
    return response.data?.count ?? 0
  },

  markRead: async (id: string): Promise<InAppNotification> => {
    const response = await api.post<InAppNotification>(`/notifications/${id}/read`, {})
    return response.data
  },

  markAllRead: async (): Promise<void> => {
    await api.post('/notifications/read-all', {})
  },

  getDeliveries: async (params?: { per_page?: number }): Promise<{
    data: NotificationDelivery[]
    meta: { total: number; current_page: number; per_page: number; last_page: number }
  }> => {
    const response = await api.get<NotificationDelivery[]>('/notifications/deliveries', { params })
    return { data: response.data ?? [], meta: response.meta ?? { total: 0, current_page: 1, per_page: 15, last_page: 1 } }
  },
}