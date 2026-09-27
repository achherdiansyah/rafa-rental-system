export type TimesheetStatus = 'DRAFT' | 'SUBMITTED' | 'APPROVED' | 'REJECTED'

export interface TimesheetSignature {
  id: number
  document_type: string
  file_name: string
  mime_type: string
  file_size: number
  url: string | null
  uploaded_by: number | null
  created_at: string
}

export interface Timesheet {
  id: number
  rental_detail_id: number
  report_date: string
  start_hm: number
  end_hm: number
  break_minutes: number
  total_work_hours: number
  standby_hours: number
  breakdown_hours: number
  operator_name: string | null
  notes: string | null
  signature_reference: string | null
  status: TimesheetStatus
  signature?: TimesheetSignature | null
  rental?: {
    rental_id: number
    booking_code: string | null
    project_name: string | null
    unit_id: number | null
    unit_serial: string | null
    unit_plate: string | null
  }
  created_at: string
}

export interface TimesheetRevision {
  id: number
  timesheet_id: number
  version: number
  old_start_hm: number
  old_end_hm: number
  revision_reason: string
  revised_by: number
  revised_by_name: string | null
  created_at: string
}