# Spesifikasi Approval & Unit Assignment (Phase 8F) - RAFA Rental System

Dokumentasi implementasi alur persetujuan booking oleh Admin/Owner, penolakan dengan alasan, penugasan unit fisik (physical unit assignment), riwayat assignment, dan fondasi penggantian unit (unit replacement).

---

## 1. Alur (Flow)

```text
PENDING_APPROVAL
   ├── Approve (Admin/Owner) ──► APPROVED
   │                              └── Assign Units (Admin) ──► unit → ASSIGNED
   │                                 └── Replace Unit (Admin) ──► history + unit baru
   └── Reject (Admin/Owner) ──► REJECTED (wajib alasan min. 10 karakter)
```

- **Invoice / Payment / Refund:** Sengaja **tidak** diimplementasikan pada fase ini (BR-021 ditunda ke fase lanjutan).

---

## 2. Endpoints (Admin/Owner)

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/bookings/{booking}/approve` | `ADMIN`, `OWNER` | `PENDING_APPROVAL → APPROVED`; audit `BOOKING_APPROVED`. |
| `POST` | `/api/v1/bookings/{booking}/reject` | `ADMIN`, `OWNER` | `PENDING_APPROVAL → REJECTED`; body `{rejection_reason}` (min 10 karakter); audit `BOOKING_REJECTED`. |
| `POST` | `/api/v1/bookings/{booking}/assign-units` | `ADMIN` only | Memasang unit fisik ke detail booking; audit `UNITS_ASSIGNED`. |
| `POST` | `/api/v1/bookings/{booking}/assignments/{assignment}/replace` | `ADMIN` only | Penggantian unit; audit `UNIT_REPLACED`. |

---

## 3. Aturan Validasi & Pengendalian Konflik

### Approve / Reject
- Hanya transisi dari status `PENDING_APPROVAL`. Status lain → `409 INVALID_STATE_TRANSITION`.
- Penolakan wajib menyertakan alasan ≥ 10 karakter (`422 VALIDATION_ERROR` bila kurang).

### Assign Units (`AssignBookingUnitsAction`)
- Hanya booking berstatus `APPROVED` boleh menerima assignment.
- **Guard Kuantitas:** total unit yang dipasang pada satu detail **harus sama persis** dengan `detail.quantity`.
- **Guard Model:** unit wajib berasal dari model yang sama dengan detail (`unit.equipment_model_id == detail.equipment_model_id`).
- **Guard Availability:** unit divalidasi via `EquipmentAvailabilityService::isUnitAvailable()` (buffer-aware).
- **Pessimistic Locking:** baris unit dikunci `FOR UPDATE` dalam satu transaksi DB → transaksi paralel yang menyentuh unit sama akan mengantre hingga commit/rollback (cegah *double-booking* unit fisik).
- Setelah assignment: dibuat `booking_unit_assignments` (`is_current = true`, `status = ASSIGNED`) dan status unit → `ASSIGNED`.

### Replace Unit (`ReplaceUnitAssignmentAction`)
- Retire assignment lama: `is_current = false`, `status = REPLACED`, catat `replaced_reason` (sebagai **riwayat assignment**).
- Unit lama dikirim ke `MAINTENANCE`.
- Unit pengganti harus `AVAILABLE`, model identik, dan bebas konflik pada periode booking.
- Dibuat assignment baru `is_current = true`; unit pengganti → `ASSIGNED`.

---

## 4. Audit Trail

Seluruh transisi & mutasi assignment dicatat via `AuditLogger::log()`:
`BOOKING_APPROVED`, `BOOKING_REJECTED` (with reason), `UNITS_ASSIGNED`, `UNIT_REPLACED`.

---

## 5. Antarmuka Admin (`/admin/bookings`)

- **AdminBookingsPage** — antrean booking dengan filter status (PENDING_APPROVAL / APPROVED / REJECTED / dsb).
- Aksi **Setujui** / **Tolak** (modal alasan) pada booking `PENDING_APPROVAL`.
- Aksi **Tugaskan Unit** pada booking `APPROVED` — modal per-detail dengan daftar unit `AVAILABLE` (checkbox), indikator pemenuhan kuota.
- Alasan penolakan ditampilkan pada kartu booking `REJECTED`.

---

## 6. Hasil Pengujian

**Backend (`AdminBookingApprovalTest` — 11 kasus):**
- Approve PENDING_APPROVAL → APPROVED; Reject dengan alasan → REJECTED.
- USER dilarang approve/reject (`403`); non-`PENDING_APPROVAL` ditolak (`409 INVALID_STATE_TRANSITION`).
- Reject tanpa alasan cukup panjang → `422` (validasi).
- Assign unit valid → assignment tersimpan + unit `ASSIGNED`.
- Kuantitas tidak tepat / unit sudah ter-commit / unit model salah → `409 BUSINESS_RULE_VIOLATION`.
- OWNER tidak boleh assign unit (`403`, admin-exclusive).
- Replacement: riwayat lama tersimpan (`is_current=false`, `REPLACED`), unit lama → `MAINTENANCE`, unit baru → `ASSIGNED`.

**Frontend (`AdminBookingQueue.test.tsx` — 4 kasus):** render antrean, approve, gate alasan reject (min 10), buka modal assign.

**Total:** Backend **264 tests (941 assertions)** • Pint 100% clean. Frontend **76 tests (19 files)** • build TypeScript clean.