# 05 — Scheduler, Queue & Operational Reliability (Phase 13E)

Konfigurasi scheduler/jobs agar andal di shared hosting, tanpa Redis/Horizon.

## Scheduler (`routes/console.php`)

| Command | Jadwal | Idempotensi |
|---|---|---|
| `bookings:expire` + slot release | every minute, `withoutOverlapping` | status diubah ke EXPIRED; run berikutnya melewati (WHERE status PENDING) |
| `invoices:expire` (24h → OVERDUE) | every minute, `withoutOverlapping` | WHERE status ISSUED/UNPAID & due<=now |
| `outstanding:remind` (WhatsApp) | daily 07:30, `withoutOverlapping` | guard **same-day**: tidak ada delivery OUTSTANDING_REMINDER utk customer di hari yg sama |
| `db:backup` (mysqldump → privat) | daily 01:00, `withoutOverlapping` | file timestamp unik; tanpa overlap |

Semua menulis log ke `storage/logs/scheduler.log`.

## Queue (database)

- `SendWhatsAppNotification` (channel `database`): **fail-fast `$tries = 1`**
  utk mencegah job di-retry yang bisa menduplikasi delivery; kegagalan
  selalu dicatat sebagai delivery `FAILED` + `Log::warning`
  (`WHATSAPP_DELIVERY_ERROR`).
- Tanpa Redis/Horizon; worker cukup `php artisan queue:work database --once`
  (atau `--queue=default --stop-when-empty`) via cron opsional.

## Cron untuk Shared Hosting

Satu baris cron menjalankan scheduler Laravel:

```
* * * * * /usr/bin/php /path/to/backend/artisan schedule:run >> /path/to/backend/storage/logs/scheduler.log 2>&1
```

(opsional, bila WA async dipakai)
```
* * * * * /usr/bin/php /path/to/backend/artisan queue:work database --once --tries=1 --max-time=60 >> /path/to/backend/storage/logs/queue.log 2>&1
```

## Logging Failure

- `ExpireBookingsCommand`: error per-booking dicatat `Log::warning`
  (`AUDIT [BOOKING_EXPIRY_SKIP]`) — tidak lagi silent.
- Invoices/remind idempotent; reminder sudat di-guard per hari.

## Test — `SchedulerReliabilityTest` (4 kasus, 16 assertions)

- Keempat command terdaftar di scheduler (utk shared hosting).
- Expiry booking & invoice: run dua kali → hasil sama (idempotent, tanpa
  proses ulang).
- Reminder outstanding: run dua kali sehari → hanya 1 delivery (anti-duplikat).
- Job WA: `tries=1` (fail-fast, tanpa retry ganda).

**Backend total 451 passed (2465 assertions)**; Pint clean.

## Files

- `routes/console.php`
- `app/Console/Commands/{SendOutstandingRemindersCommand,ExpireBookingsCommand,DatabaseBackupCommand}.php`
- `app/Jobs/SendWhatsAppNotification.php` ($tries=1 + logging)
- `tests/Feature/Reliability/SchedulerReliabilityTest.php`