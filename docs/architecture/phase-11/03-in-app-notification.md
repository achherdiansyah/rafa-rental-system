# 03 — In-App Notification (Phase 11D)

Notifikasi dalam aplikasi berbasis Laravel Notification (database channel).

## Event yang Dipicu

| Event | Recipient | Pemicu |
|---|---|---|
| `BOOKING_APPROVED` / `BOOKING_REJECTED` | pemilik booking | `ApproveBookingAction` / `RejectBookingAction` |
| `BOOKING_CANCELLED` / `BOOKING_EXPIRED` | pemilik booking | `CancelBookingAction` / `ExpireBookingAction` |
| `INVOICE_ISSUED` | pemilik booking | `IssueInvoiceAction` |
| `INVOICE_OVERDUE` | pemilik booking | cron `invoices:expire` |
| `PAYMENT_SUBMITTED` | ADMIN | `SubmitPaymentAction` |
| `PAYMENT_APPROVED` / `PAYMENT_REJECTED` | pemilik booking | `VerifyPaymentAction` |
| `REFUND_PENDING` | OWNER | `RefundRegistrationService` (perlu approval) |
| `REFUND_APPROVED / PROCESSING / COMPLETED / FAILED` | pemilik booking | `RefundLifecycleService` |

## Mekanisme

- **Laravel Notification**: `App\Notifications\SystemNotification` via channel
  `database` → tabel `notifications` bawaan (id uuid, `notifiable` morph,
  `data` JSON, `read_at`).
- **Type + related entity**: payload berisi `event`, `entity_type` (class),
  `entity_id`, `message`, `link`, `sent_at`.
- **Unread/read**: `read_at` null/timestamp.
- **Scoping**: setiap notifikasi terikat `notifiable` (user). API hanya
  memuat/menandai milik user yang sedang login (`notifications()->where(id…).first()`
  → 404 bila milik orang lain). Admin/Owner melihat notifikasi yang ditujukan
  ke akunnya (dikirim `sendToRole`).
- **Queue**: tidak bergantung Redis. Pengiriman via channel database sinkron
  (insert langsung); `QUEUE_CONNECTION=database` tersedia bila ingin lalu
  di-queue — poli ini eksplisit & andal.
- Data receiver ditentukan eksplisit oleh pemanggil (user/ADMIN/OWNER).

## API

```
GET  /api/v1/notifications                  list (paginasi; filter ?read=true|false)
GET  /api/v1/notifications/unread-count     jumlah belum dibaca
POST /api/v1/notifications/{id}/read        tandai dibaca (hanya milik sendiri)
POST /api/v1/notifications/read-all         tandai semua dibaca
```

## Test Result — `NotificationTest` (6 kasus, 27 assertions)

- Service → notifikasi DB dengan related entity (event/entity_type/entity_id/
  message) + unread count 1.
- Unread count, mark single read → count berkurang; read-all → 0; filter
  `?read=true`.
- Scoping: user hanya melihat miliknya; mark-read notifikasi user lain → 404,
  `read_at` tetap null.
- `invoices:expire` → `INVOICE_OVERDUE` ke pemilik booking.
- `ApproveBookingAction` → `BOOKING_APPROVED`; `VerifyPaymentAction::approve`.
- Rantai refund (approve→process→complete) → `REFUND_PROCESSING` & `REFUND_COMPLETED`
  ke pelanggan.

## Files

- `app/Notifications/SystemNotification.php`
- `app/Support/NotificationService.php`
- `app/Http/Controllers/Api/V1/NotificationController.php`
- `app/Http/Resources/NotificationResource.php`
- Wiring di action: Booking (approve/reject/cancel/expire), `IssueInvoiceAction`,
  `SubmitPaymentAction`, `VerifyPaymentAction`, `ExpireInvoicesCommand`,
  `RefundRegistrationService`, `RefundLifecycleService`
- `routes/api.php` (group `notifications`)
- `tests/Feature/Notification/NotificationTest.php`