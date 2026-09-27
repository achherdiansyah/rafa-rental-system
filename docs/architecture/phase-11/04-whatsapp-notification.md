# 04 — WhatsApp Notification Integration (Phase 11E)

Lapisan integrasi WhatsApp dengan provider yg pluggable dan audit delivery.

## Prinsip

- **Provider tidak di-hardcode** — abstraction `WhatsAppGateway`; provider dipilih
  lewat `services.whatsapp.provider` (`WHATSAPP_PROVIDER`). Belum ada provider →
  `NullWhatsAppGateway`.
- **Business event terpisah dari channel delivery** — aksi bisnis menembak
  `notify(event, user, message)`; logika channel/provider berhenti di
  `WhatsAppNotifier`.
- **Delivery status/error disimpan** — tabel `notification_deliveries`
  (`SENT | FAILED | SKIPPED | QUEUED`, provider, error, sent_at, recipient).
- **Queue**: async via **database queue** saat `WHATSAPP_QUEUE=true` (job
  `SendWhatsAppNotification`, connection `database` — tanpa Redis); default
  sinkron.
- **Tanpa fake success** — saat provider tidak terkonfigurasi status adalah
  `SKIPPED` (pesan reason), bukan SENT.
- **Tetap normal tanpa konfigurasi** — semua alur bisnis meneruskan; kegagalan
  ditekan (tidak pernah melempar ke action).

## Arsitektur

| Komponen | Peran |
|---|---|
| `WhatsAppGateway` (interface) | `name()` + `send(WhatsAppMessage): {sent, error}` |
| `NullWhatsAppGateway` | default; `sent:false` + reason (jujur) |
| `WhatsAppNotifier` | pilih recipient/phone/provider; sinkron atau DB-queue; persist delivery |
| `SendWhatsAppNotification` (job, `ShouldQueue`) | async delivery + log SENT/FAILED |
| `NotificationDelivery` (model) + migration `...000013` | log audit delivery |
| `SendOutstandingRemindersCommand` (`outstanding:remind`) | reminder outstanding utk customer lewat deadline |
| config `services.whatsapp` | `provider`, `phone_number`, `queue` |

Provider lain tinggal `implements WhatsAppGateway` + set `WHATSAPP_PROVIDER`
ke class-nya (di-bind otomatis).

## Event yang Terhubung

- `PAYMENT_APPROVED` / `PAYMENT_REJECTED` → `VerifyPaymentAction`
- `INVOICE_ISSUED` → `IssueInvoiceAction`
- `INVOICE_DEADLINE` → cron `invoices:expire` (OVERDUE)
- `REFUND_COMPLETED` → `RefundLifecycleService`
- `OUTSTANDING_REMINDER` → `outstanding:remind` (customer dengan invoice OVERDUE)

## Test Result — `WhatsAppNotificationTest` (6 kasus, 23 assertions)

- Provider belum dikonfigurasi → `SKIPPED` + alasan, `sent_at` null; sistem
  tetap berjalan (event berikutnya tetap tercatat).
- Recipient tanpa nomor → `SKIPPED`.
- Gateway fake: `SENT` (provider tercatat, `sent_at` terisi) & `FAILED`
  (`error` tersimpan, `sent_at` null).
- Wiring: `VerifyPaymentAction::approve` menghasilkan delivery `PAYMENT_APPROVED`
  (SKIPPED, bukan SENT palsu) → bukti pemisahan bisnis-channel.
- Wiring: rantai refund → `REFUND_COMPLETED` delivery utk pelanggan.
- `outstanding:remind` → delivery `OUTSTANDING_REMINDER` utk debtor.

## Files

- `app/Services/WhatsApp/{WhatsAppGateway,NullWhatsAppGateway,WhatsAppMessage,WhatsAppNotifier}.php`
- `app/Jobs/SendWhatsAppNotification.php`
- `app/Models/NotificationDelivery.php`
- migration `2026_09_25_000013_create_notification_deliveries_table.php`
- `app/Console/Commands/SendOutstandingRemindersCommand.php`
- `config/services.php` (blok whatsapp) + binding di `AppServiceProvider`
- Wiring: `VerifyPaymentAction`, `IssueInvoiceAction`, `ExpireInvoicesCommand`,
  `RefundLifecycleService`
- `tests/Feature/Notification/WhatsAppNotificationTest.php`