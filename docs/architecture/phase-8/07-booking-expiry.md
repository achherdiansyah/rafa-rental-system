# Spesifikasi Booking Expiry & Slot Release (Phase 8G) - RAFA Rental System

Dokumentasi implementasi mekanisme kadaluwarsa booking berdasarkan tenggat pembayaran, pelepasan slot unit fisik otomatis, perpanjangan tenggat manual oleh Admin, dan integrasi boundary status pembayaran.

---

## 1. Aturan Tenggat Pembayaran

- **Payment Deadline:** `payment_deadline_at = approved_at + 24 jam` (payment grace default 24 jam, konfigurabel di `config/availability.php`).
- Jika deadline terlewati **dan** pembayaran belum memenuhi syarat, booking beralih ke status **`EXPIRED`**.
- **Expired semantics:**
  - Seluruh assignment saat ini dilepas: `is_current = false` + `status = CANCELLED` (riwayat dipertahankan).
  - Unit fisik yang berstatus `ASSIGNED` dikembalikan ke **`AVAILABLE`**.
  - Kapasitas availability otomatis dipulihkan di pool publik.
- **No premature expiry:** Booking hanya expire jika `deadline < now()`. Deadline yang masih di depan (atau bertepatan di batas) tidak mengubah status.

---

## 2. Integration Boundary Pembayaran (tanpa Fake Invoice/Payment)

Invoice & payment engine akan diimplementasikan pada **Phase 10**. Fase ini tidak membuat tabel invoice/payment tiruan, melainkan mendefinisikan *seam integrasi*:

```text
PaymentStatusProvider (interface)          <- boundary kalkulasi expiry
   └── DeferredPaymentStatusProvider       <- implementasi default Phase 8G
         ├── isSatisfied(booking) : bool    (read payment_met_at)
         └── paymentDeadline(booking)      (read payment_deadline_at / approved_at+grace)
```

Phase 10 akan menyuntikkan implementasi berbasis `invoices`/`payments` nyata tanpa mengubah scheduler ekspirasi.

Kolom booking baru (dari migrasi `...000004`):
- `approved_at` (timestamp) — diisi saat approve.
- `payment_deadline_at` (timestamp) — diisi saat approve, menjadi basis cmp.
- `payment_met_at` (timestamp) — diisi boundary pembayaran saat terpenuhi (Phase 10).

---

## 3. Alur Kerja

```text
Approved (approve) ──► approved_at + deadline set (approved_at + 24h)
     │
     ├── Payment satisfied (Phase 10 boundary) ──► payment_met_at set → TIDAK expire
     ├── Deadline passed & unmet ──► EXPIRED
     │       └── assignments released (history) ──► units → AVAILABLE
     └── Manual extension (Admin) ──► payment_deadline_at += N hours (audited)
```

---

## 4. Scheduler / Cron (tanpa Redis/Horizon)

Command `bookings:expire` dijalankan via **Laravel Scheduler** (cron `php artisan schedule:run`):

```php
Schedule::command('bookings:expire')->everyMinute()->withoutOverlapping()
```

- Menyeleksi booking berstatus `APPROVED` / `PAYMENT_PENDING` dengan `payment_met_at = null`.
- Mengandalkan boundary `PaymentStatusProvider` untuk keputusan per-booking.
- Log event `BOOKING_AUTO_EXPIRED` di audit.

---

## 5. Manual Extension (Policy-Gated & Audited)

- Endpoint: `POST /api/v1/bookings/{booking}/extend-deadline`
- Body: `{ additional_hours: int (min 1), reason: string (min 5) }`
- Actor: **ADMIN / OWNER** (`BookingPolicy::extendDeadline`). `USER` → `403`.
- Perpanjangan **tidak pernah** direset oleh penolakan pembayaran — hanya aksi eksplisit ini yang mengubah deadline.
- Audit: `PAYMENT_DEADLINE_EXTENDED` (old/new deadline, additional_hours, reason, actor).

---

## 6. Aturan Penting Lainnya

- **Payment rejection tidak mereset deadline:** rejection direpresentasikan boundary tetap `isSatisfied() = false`; booking yang lewat deadline tetap expire.
- **Completed payment tidak expire:** `payment_met_at` terisi → scheduler & action menolak expire (`409 INVALID_STATE_TRANSITION`).
- Versi batch command idempotent: rain-run hanya memproses booking yang benar-benar overdue.

---

## 7. Hasil Pengujian (`BookingExpiryTest` — 11 kasus)

| Skenario | Hasil |
|---|---|
| Expiry melepas slot & memulihkan availability | **PASSED** |
| Tidak ada premature expiry (deadline di depan) | **PASSED** |
| Batas deadline (≈ sekarang) tidak expire | **PASSED** |
| Scheduler tidak menyentuh booking ber-deadline masa depan | **PASSED** |
| Scheduler expire booking overdue + rilis slot | **PASSED** |
| Penolakan payment tidak mereset deadline | **PASSED** |
| Payment terpenuhi mencegah expiry | **PASSED** |
| Booking terbayar dilewati scheduler | **PASSED** |
| Admin dapat memperpanjang deadline (audited) | **PASSED** |
| USER tidak dapat memperpanjang (`403`) | **PASSED** |
| Validasi extension (hours & reason) | **PASSED** |

**Total Backend Suite:** 275 tests passed (969 assertions) • Laravel Pint 100% clean.