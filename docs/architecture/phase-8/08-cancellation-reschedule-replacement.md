# Spesifikasi Cancellation, Reschedule & Unit Replacement (Phase 8H) - RAFA Rental System

Dokumentasi implementasi pembatalan booking bersyarat, permintaan reschedule, dan penggantian unit fisik — lengkap dengan guard state machine, validasi availability, riwayat audit, dan boundary refund (eksekusi refund ditunda ke Phase 10).

---

## 1. Cancellation (Pembatalan)

Matriks status vs pelaku:

| Status Booking | `USER` | `ADMIN`/`OWNER` | Catatan |
|---|---|---|---|
| `DRAFT` / `SUBMITTED` / `PENDING_APPROVAL` | ✅ Langsung | ✅ | Pra-pembayaran. |
| `APPROVED` / `PAYMENT_PENDING` (payment belum selesai) | ✅ Langsung | ✅ | Pra-transfer. |
| `APPROVED` / `PAYMENT_PENDING` (payment selesai) | ❌ Blocked | ✅ | `refund_pending` via boundary. |
| `CONFIRMED` | ❌ Blocked | ✅ | Approval Admin wajib. |
| `DISPATCHED` / `ARRIVED` / `ONGOING` | ❌ | ❌ | **Dilarang keras** (per T-B06). |
| `CANCELLED` / `COMPLETED` / `EXPIRED` / `REJECTED` | ❌ | ❌ | Terminal. |

- **Side effect cancellation:** status → `CANCELLED`, `cancelled_at` + `cancellation_reason` tercatat, deadline dibersihkan, slot assignment dilepas (`is_current=false`, `CANCELLED`), unit → `AVAILABLE`, riwayat utuh.
- **Refund boundary:** pembatalan pasca-bayar memanggil `RefundBoundary::registerPendingRefund()` (no-op fase ini; eksekusi refund → Phase 10).
- Audit: `BOOKING_CANCELLED` (aktor, is_staff, reason, refund_pending, released_assignments).

### Endpoint
`POST /api/v1/bookings/{booking}/cancel` — body `{ reason }` (min 5 karakter). Actor USER (own, pra-bayar) / ADMIN / OWNER.

---

## 2. Reschedule

- **Wewenang:** Owner booking (`USER`) atau staff; hanya untuk status `APPROVED` / `PAYMENT_PENDING` / `CONFIRMED`.
- **Validasi:** rentang baru wajib `end >= start`; **availability + buffer** divalidasi ulang via `BookingAvailabilityPrecheckService` (menolak `BUSINESS_RULE_VIOLATION` jika bentrok).
- **Efek:** seluruh `booking_details` diperbarui ke tanggal baru; status kembali `PENDING_APPROVAL` (perlu persetujuan Admin ulang); **`reschedule_history` (JSON) menyimpan tanggal lama→baru + alasan + aktor**.
- **Payment history aman:** `payment_met_at` / kolom pembayaran tidak disentuh.
- Audit: `BOOKING_RESCHEDULED` (old/new dates, reason).

### Endpoint
`POST /api/v1/bookings/{booking}/reschedule` — `{ new_start_date, new_end_date, reason }`.

---

## 3. Unit Replacement (Fondasi Phase 8F + UI)

- `POST /bookings/{booking}/assignments/{assignment}/replace` (Admin-only, sudah ada sejak 8F).
- Guard: booking `APPROVED`, assignment milik booking & `is_current`, unit pengganti `AVAILABLE` + model identik + `isUnitAvailable` bebas konflik; `lockForUpdate` unit pengganti.
- Riwayat assignment lama dipertahankan (`is_current=false`, `REPLACED`, reason); unit lama → `MAINTENANCE`.
- Audit: `UNIT_REPLACED` (old/new unit, reason, actor, timestamp).

---

## 4. Refund Boundary (bukan fake refund)

```text
RefundBoundary (interface) ──Phase 10──► real refund engine
   └── DeferredRefundBoundary  (no-op saat ini, tanpa tabel tiruan)
```
Catalan audit `refund_pending=true` menjadi jejak integrasi untuk Phase 10.

---

## 5. UI

- **User (`/app/bookings`)**: tombol **Batalkan** (pra-bayar, modal alasan) & **Ajukan Reschedule** (modal tanggal + alasan).
- **Admin (`/admin/bookings`)**: kartu APPROVED menampilkan serial unit ter-assign dengan aksi **Ganti** → modal pilih unit AVAILABLE + alasan (memanggil endpoint replace).

---

## 6. Hasil Pengujian (`BookingCancellationRescheduleTest` — 13 kasus)

| Skenario | Hasil |
|---|---|
| Cancel pra-bayar oleh USER | **PASSED** |
| Cancel melepas assigned slot | **PASSED** |
| USER tidak bisa cancel pasca-bayar | **PASSED** |
| ADMIN bisa cancel pasca-bayar (refund boundary) | **PASSED** |
| CONFIRMED wajib Admin | **PASSED** |
| DISPATCHED tidak bisa cancel (Admin pun tidak) | **PASSED** |
| User lain tidak bisa cancel | **PASSED** |
| Validasi alasan cancel | **PASSED** |
| Reschedule → PENDING_APPROVAL + history tanggal | **PASSED** |
| Reschedule menolak jendela tidak tersedia | **PASSED** |
| Bentrok buffer menolak reschedule | **PASSED** |
| Reschedule tidak mengubah payment metadata | **PASSED** |
| Unit replacement (guard + history + audit) | **PASSED** |

**Total Backend Suite:** 288 tests passed (1007 assertions) • Pint 100% clean. Frontend 76 tests (19 files) • build clean.