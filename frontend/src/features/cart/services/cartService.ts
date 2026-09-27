import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Cart, AddCartItemInput, UpdateCartItemInput } from '@/types/cart'

export const cartService = {
  async getCart(): Promise<ApiResponse<Cart>> {
    const response = await api.get<Cart>('/cart')
    return response
  },

  async addItem(data: AddCartItemInput): Promise<ApiResponse<Cart>> {
    const response = await api.post<Cart, AddCartItemInput>('/cart/items', data)
    return response
  },

  async updateItem(id: number, data: UpdateCartItemInput): Promise<ApiResponse<Cart>> {
    const response = await api.put<Cart, UpdateCartItemInput>(`/cart/items/${id}`, data)
    return response
  },

  async removeItem(id: number): Promise<ApiResponse<Cart>> {
    const response = await api.delete<Cart>(`/cart/items/${id}`)
    return response
  },

  async clearCart(): Promise<ApiResponse<Cart>> {
    const response = await api.delete<Cart>('/cart')
    return response
  },

  async updateLocation(projectLocationId: number): Promise<ApiResponse<Cart>> {
    const response = await api.put<Cart, { project_location_id: number }>('/cart/location', {
      project_location_id: projectLocationId,
    })
    return response
  },
}