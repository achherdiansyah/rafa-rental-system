export interface InAppNotification {
  id: string
  type: string
  event: string | null
  entity_type: string | null
  entity_id: number | string | null
  message: string
  link: string | null
  read_at: string | null
  created_at: string
}

export interface NotificationDelivery {
  id: number
  event: string
  channel: string
  recipient_id: number | null
  recipient_phone: string | null
  provider: string | null
  status: 'SENT' | 'FAILED' | 'SKIPPED' | 'QUEUED'
  error: string | null
  sent_at: string | null
  created_at: string
}