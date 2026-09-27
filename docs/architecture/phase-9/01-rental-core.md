# Spesifikasi Rental Core (Phase 9A) - RAFA Rental System

Dokumentasi implementasi domain eksekusi sewa lapangan: pembuatan rental dari booking `CONFIRMED`, relasi rental-detail ke assignment unit fisik, siklus hidup state machine, dan sinkronisasi status unit.

---

## 1. Prinsip & Aturan

1. **Satu rental berasal dari booking yang valid** — booking wajib berstatus `CONFIRMED`; satu booking hanya dapat menghasilkan **satu** rental (relasi `rentals.booking_id` UNIQUE).
2. **Physical unit harus berasal dari assignment** — setiap `rental_detail.assignment_id` MERUJUK ke `booking_unit_assignments` (is_current), sehingga unit fisik divisualkan via `assignment → equipment_units`. Satuan rental tidak menyimpan unit secara terpisah melainkan melalui jejak assignment (provenance).
3. **Relasi lengkap:** Rental → Booking → Project Location; Rental → RentalDetail → Assignment → Unit (+ Model).
4. **Sinkronisasi status unit:** setiap transisi rental juga menggerakkan status unit fisik agar konsisten dengan kondisi lapangan.
5. **Inspirasi buffer/availability:** tidak membuat invoice/payment; berfokus eksekusi & audit.

---

## 2. State Machine Rental

```text
[Booking CONFIRMED]
      │ create rental
      ▼
  ASSIGNED ──dispatch──► DISPATCHED ──arrive──► ARRIVED
                                                    │ start (BAST check-in, started_at)
                                                    ▼
  COMPLETED ◄──inspect── RETURN_INSPECTED ◄──return── DEMOBILIZING ◄──… ONGOING
```

| Transisi | Endpoint | Unit Status | Metadata |
|---|---|---|---|
| `ASSIGNED → DISPATCHED` | `dispatch` | `MOBILIZING` | audit `RENTAL_DISPATCHED` |
| `DISPATCHED → ARRIVED` | `arrive` | `ON_SITE` | audit `RENTAL_ARRIVED` |
| `ARRIVED → ONGOING` | `start` | `ON_SITE` | `started_at` + audit `RENTAL_ONGOING` |
| `ONGOING → DEMOBILIZING` | `return` | `DEMOBILIZING` | audit `RENTAL_DEMOBILIZING` |
| `DEMOBILIZING → RETURN_INSPECTED` | `inspect` | `RETURN_INSPECTION` | audit `RENTAL_RETURN_INSPECTED` |
| `RETURN_INSPECTED → COMPLETED` | `complete` | `AVAILABLE` | `completed_at` + audit `RENTAL_COMPLETED` |

Transisi di luar peta (mis. lompat `ASSIGNED → ONGOING`) ditolak `409 INVALID_STATE_TRANSITION`.

---

## 3. Skema Data (sesuai ERD)

### `rentals`
`id`, `booking_id` (UNIQUE FK → bookings, RESTRICT), `status`, `started_at`, `completed_at`, timestamps.

### `rental_details`
`id`, `rental_id` (FK → rentals, CASCADE), `assignment_id` (UNIQUE FK → booking_unit_assignments, RESTRICT), `check_in_hm`, `check_out_hm`, `condition_notes`, `status`, timestamps.

---

## 4. Endpoints API

| Method | URI | Actor | Deskripsi |
|---|---|---|---|
| `POST` | `/api/v1/rentals` | `ADMIN` | Buat rental dari `{ booking_id }` (booking CONFIRMED + unit ter-assign). |
| `GET` | `/api/v1/rentals` | `USER`(own), `ADMIN`, `OWNER` | List rental terpaginasi (filter `status`, `booking_id`). |
| `GET` | `/api/v1/rentals/{rental}` | `USER`(own), `ADMIN`, `OWNER` | Detail rental + bookings + unit. |
| `POST` | `/api/v1/rentals/{rental}/{target}` | `ADMIN` | Transisi lifecycle (`dispatch`/`arrive`/`start`/`return`/`inspect`/`complete`). |

---

## 5. Arsitektur

| Layer | Komponen |
|---|---|
| Action | `CreateRentalFromBookingAction` (guard CONFIRMED, assignment ada, unik), `TransitionRentalAction` (peta transisi + sync unit + audit) |
| Policy | `RentalPolicy` (view: owner/staff; operate: admin-only) |
| Resources | `RentalResource`, `RentalDetailResource` (termasuk unit serial/plat/status) |
| Controller | `RentalController` (tipis; delegasi ke action) |

---

## 6. Hasil Pengujian (`RentalCoreTest` — 9 kasus)

| Skenario | Hasil |
|---|---|
| Admin membuat rental dari booking CONFIRMED + 2 detail assignment | **PASSED** |
| Rental wajib booking CONFIRMED (APPROVED → 409) | **PASSED** |
| Booking tanpa assignment → 409 `BUSINESS_RULE_VIOLATION` | **PASSED** |
| Duplikat rental per booking diblokir 409 | **PASSED** |
| Full state machine (dispatch→…→complete) menyinkronkan status unit | **PASSED** |
| Transisi tidak valid (lompat langsung ONGOING) → 409 `INVALID_STATE_TRANSITION` | **PASSED** |
| USER dapat lihat rental sendiri tetapi tidak bisa operate (403) | **PASSED** |
| USER tidak bisa lihat rental milik user lain (403) | **PASSED** |
| OWNER read-only list | **PASSED** |

**Total Backend Suite:** 300 tests passed (1094 assertions) • Pint 100% clean.