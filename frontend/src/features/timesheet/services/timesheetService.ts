import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { Timesheet, TimesheetRevision, TimesheetSignature } from '@/types/timesheet'

export interface TimesheetPayload {
  rental_detail_id: number
  report_date: string
  start_time?: string
  end_time?: string
  start_hm?: number
  end_hm?: number
  break_minutes?: number
  standby_hours?: number
  breakdown_hours?: number
  operator_name?: string
  notes?: string
}

export interface RevisePayload {
  start_time?: string
  end_time?: string
  start_hm?: number
  end_hm?: number
  break_minutes?: number
  standby_hours?: number
  breakdown_hours?: number
  notes?: string
  reason: string
}

export const timesheetService = {
  async getTimesheets(params?: { rental_detail_id?: number; status?: string; page?: number; per_page?: number }): Promise<ApiResponse<Timesheet[]>> {
    const response = await api.get<Timesheet[]>('/timesheets', { params })
    return response
  },

  async getTimesheet(id: number): Promise<ApiResponse<Timesheet>> {
    const response = await api.get<Timesheet>(`/timesheets/${id}`)
    return response
  },

  async create(payload: TimesheetPayload): Promise<ApiResponse<Timesheet>> {
    const response = await api.post<Timesheet, TimesheetPayload>('/timesheets', payload)
    return response
  },

  async submit(id: number): Promise<ApiResponse<Timesheet>> {
    const response = await api.post<Timesheet>(`/timesheets/${id}/submit`, {})
    return response
  },

  async sign(id: number, file: File): Promise<ApiResponse<TimesheetSignature>> {
    const formData = new FormData()
    formData.append('signature', file)

    const response = await api.post<TimesheetSignature>(`/timesheets/${id}/signature`, formData)
    return response
  },

  async approve(id: number): Promise<ApiResponse<Timesheet>> {
    const response = await api.post<Timesheet>(`/timesheets/${id}/approve`, {})
    return response
  },

  async reject(id: number, reason: string): Promise<ApiResponse<Timesheet>> {
    const response = await api.post<Timesheet, { reason: string }>(`/timesheets/${id}/reject`, { reason })
    return response
  },

  async revise(id: number, payload: RevisePayload): Promise<ApiResponse<Timesheet>> {
    const response = await api.put<Timesheet, RevisePayload>(`/timesheets/${id}/revise`, payload)
    return response
  },

  async getRevisions(id: number): Promise<ApiResponse<TimesheetRevision[]>> {
    const response = await api.get<TimesheetRevision[]>(`/timesheets/${id}/revisions`)
    return response
  },
}