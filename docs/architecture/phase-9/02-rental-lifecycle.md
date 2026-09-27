# Spesifikasi Rental Lifecycle — Dispatch, Arrival & Ongoing (Phase 9B) - RAFA Rental System

Dokumentasi transisi operasional awal rental: pengiriman unit (DISPATCH), konfirmasi kedatangan ke proyek (ARRIVAL), dan konfirmasi dimulainya pekerjaan (ONGOING), berikut UI Admin dan pengujiannya.

---

## 1. Definisi Transisi

| Transisi | Arti | Unit Status | Metadata Audit |
|---|---|---|---|
| `Bonding CONFIRMED → rental ASSIGNED → DISPATCHED` | Unit fisik dikirim dari pool ke lokasi proyek | `MOBILIZING` | `RENTAL_DISPATCHED` |
| `DISPATCHED → ARRIVED` | Unit tiba dan terkonfirmasi di lokasi proyek | `ON_SITE` | `RENTAL_ARRIVED` |
| `ARRIVED → ONGOING` | Pekerjaan/rental dikonfirmasi mulai (BAST check-in; `started_at` tercatat) | `ON_SITE` | `RENTAL_ONGOING` |

**Aturan kunci:**
- **Dispatch ≠ Ongoing** — `dispatch` hanya menggeser ke `DISPATCHED` (tanpa `started_at`); start menuju `ONGOING` adalah transisi terpisah.
- **Operational start memerlukan konfirmasi Admin** — endpoint `start` hanya untuk role `ADMIN` (`RentalPolicy::operate`).
- **Illegal transition ditolak** — setiap transisi non-idempotent diverifikasi terhadap *allowed map*; lompatan/lompatan ulang → `409 INVALID_STATE_TRANSITION`.
- **Semua transisi diaudit** via `AuditLogger` (`RENTAL_DISPATCHED`, `RENTAL_ARRIVED`, `RENTAL_ONGOING`) beserta aktor & timestamp.

---

## 2. UI Admin (`/admin/rentals`)

`AdminRentalsPage` menyajikan antrean rental dengan aksi kontekstual per status:
- `ASSIGNED` → tombol **Kirim Unit (Dispatch)**
- `DISPATCHED` → tombol **Konfirmasi Tiba (Arrival)**
- `ARRIVED` → tombol **Konfirmasi Mulai (Ongoing)**

Tiap aksi melewati `ConfirmDialog` (loading/error/empty state, toast feedback, SPA navigation).

---

## 3. Endpoints (reuse Phase 9A)

| Method | URI | Actor |
|---|---|---|
| `POST` | `/api/v1/rentals/{rental}/dispatch` | `ADMIN` |
| `POST` | `/api/v1/rentals/{rental}/arrive` | `ADMIN` |
| `POST` | `/api/v1/rentals/{rental}/start` | `ADMIN` |

---

## 4. Hasil Pengujian

**Backend (`RentalLifecycleTest` — 6 kasus):**
- Urutan valid `dispatch → arrive → start` menyinkronkan unit `MOBILIZING → ON_SITE` dan menetapkan `started_at` di ONGOING — **PASSED**
- `dispatch` ≠ `ONGOING` (tanpa `started_at`) — **PASSED**
- Transisi ilegal (dispatch berulang, lompat langsung `start`/`arrive`) → `409 INVALID_STATE_TRANSITION` — **PASSED**
- Operational start hanya untuk ADMIN (USER 403) — **PASSED**
- Transisi tercatat di audit (`RENTAL_DISPATCHED`, `RENTAL_ARRIVED`) — **PASSED**

**Frontend (`RentalLifecycle.test.tsx` — 5 kasus):** render status+aksi dispatch, dispatch via confirm dialog, aksi arrival (DISPATCHED), aksi ongoing (ARRIVED), empty & error state — **PASSED**

**Quality Gate:** Backend **306 tests (1121 assertions)** • Pint 100% clean • Frontend **81 tests (20 files)** • build TypeScript clean.