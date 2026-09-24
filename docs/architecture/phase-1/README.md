# Phase 1: Architecture & Scope Lock - RAFA Rental System

---

## 1. Status Phase 1

**PHASE 1 COMPLETE.** Seluruh dokumen arsitektur, aturan bisnis, state machine, ERD, kontrak API, permission matrix, error handling, availability, pricing, ADR, dan register pending decisions telah selesai disusun.

---

## 2. Dokumen yang Dibuat

| No | File | Isi |
|---|---|---|
| 00 | `00-scope-review-v3.md` | Scope lock, blueprint PRD V3, batasan cPanel, register isu awal. |
| 01 | `01-business-rules-v3.md` | 35 Business Rules (BR-001 s.d BR-035), 20 kategori modul. |
| 02 | `02-state-machines-v3.md` | 6 State Machines (Booking, Rental, Equipment Unit, Invoice, Payment, Refund), 38 transisi, diagram Mermaid. |
| 04 | `04-erd-v3.md` | ERD Mermaid, 26 relasi kardinalitas, skema snapshot finansial, histori assignment, relasi polimorfik. |
| 05 | `05-data-dictionary-v3.md` | Kamus data 29 entitas tabel, tipe kolom, FK, constraint, index, ENUM, soft delete. |
| 06 | `06-api-contract-v3.md` | Kontrak API v1 RESTful, 18 modul, 11 endpoint booking detail, idempotensi, audit. |
| 07 | `07-permission-matrix-v3.md` | Matriks RBAC granular (USER, ADMIN, OWNER), 18 kategori kapabilitas. |
| 08 | `08-error-handling-v3.md` | Envelope JSON standar, 11+ error code domain bisnis, HTTP status mapping, contoh respons. |
| 09 | `09-availability-and-concurrency-v3.md` | Model-level & physical-unit availability, buffer MOB/DEMOB/Inspeksi, slot lifecycle, pessimistic locking, MySQL source of truth. |
| 10 | `10-pricing-and-billing-rules-v3.md` | Hourly rate 1-8 jam, actual hours, no rounding/tax/discount, All-in/Non All-in, MOB/DEMOB per unit, price snapshot, partial/overpayment, refund manual. |
| 11 | `11-architecture-decisions-v3.md` | 11 ADR (React+Vite, Laravel API, Docker optional, MySQL SOT, DB Queue, cPanel, 3 roles, Admin assignment, price versioning, audit immutability, API versioning). |
| 12 | `12-pending-business-decisions-v3.md` | 10 keputusan bisnis terbuka (PBD-01 s.d PBD-10) tanpa asumsi rekayasa. |

---

## 3. Business Rules Terkunci

35 aturan bisnis (BR-001 s.d BR-035) telah terkunci lintas 20 kategori:

- 1 booking = 1 project location
- User tidak memilih physical unit; Admin assign
- Invoice terbit hanya setelah booking disetujui
- Payment deadline 24 jam (timer invariant, tidak reset saat rejection)
- 1 invoice : N payments; 1 payment : 1 invoice
- Partial payment didukung
- Overpayment wajib verifikasi manual
- Refund manual via transfer bank
- All-in / Non All-in per equipment line
- MOB/DEMOB dihitung per physical unit
- Timesheet dapat direvisi tanpa menghapus histori lama
- Admin validasi timesheet sebelum billing

---

## 4. State Machine Summary

| Entitas | Jumlah State | State Terminal | Aturan Kritis |
|---|---|---|---|
| Booking | 13 | REJECTED, EXPIRED, CANCELLED, COMPLETED | Cancel dilarang pasca DISPATCHED |
| Rental | 9 | COMPLETED, CANCELLED | DISPATCHED != ARRIVED != ONGOING |
| Equipment Unit | 8 | DECOMMISSIONED | AVAILABLE hanya setelah RETURN_INSPECTION lulus |
| Invoice | 6 | PAID, EXPIRED, CANCELLED | Timer 24 jam immutable |
| Payment | 3 | APPROVED | Rejection tidak reset deadline |
| Refund | 6 | COMPLETED, REJECTED | Eksekusi manual oleh Admin/Owner |

---

## 5. ERD Summary

- 29 entitas tabel terdefinisi
- 26 relasi kardinalitas terdokumentasi (FK, cascade/restrict behavior)
- Monetary: `DECIMAL(15,2)` konsisten tanpa float
- Timestamp: UTC storage, WIB display
- Polymorphic: `attachments`, `activity_logs`
- Snapshot immutability: `booking_details.rental_rate_snapshot`, `invoice_details.unit_price`
- Assignment history: `booking_unit_assignments.is_current` + `status` (ASSIGNED/REPLACED/CANCELLED/COMPLETED)
- Timesheet versioning: `timesheet_revisions` append-only

---

## 6. API Summary

- Base URL: `/api/v1`
- 18 modul endpoint terdokumentasi
- 11 endpoint Booking detail (create, submit, approve, reject, cancel, reschedule, assign-units, replace-unit, extend-deadline)
- Autentikasi: Laravel Sanctum Bearer Token
- Envelope JSON konsisten (success, message, data, error_code, errors, meta)

---

## 7. Security Summary

- Sanctum token-based authentication
- Policy/Gate authorization per endpoint (IDOR prevention)
- Rate limiting (`ThrottleRequests`)
- RBAC 3 role (USER, ADMIN, OWNER) via ENUM column
- Audit log append-only (tidak dapat dihapus/diubah)
- File upload validation (mime, size limit)
- No secrets in response/logs

---

## 8. Architecture Decisions

11 ADR terkunci:

| ADR | Keputusan |
|---|---|
| ADR-001 | React + Vite + TypeScript + Tailwind (SPA statis) |
| ADR-002 | Laravel API terpisah dari frontend |
| ADR-003 | Docker hanya opsional development |
| ADR-004 | MySQL 8.4 source of truth |
| ADR-005 | Database queue (tanpa Redis/Horizon) |
| ADR-006 | Target production shared hosting cPanel |
| ADR-007 | 3 role saja (USER, ADMIN, OWNER) |
| ADR-008 | Admin unit assignment (user dilarang pilih unit fisik) |
| ADR-009 | Price versioning & financial snapshot immutability |
| ADR-010 | Audit trail append-only immutable |
| ADR-011 | API versioning `/api/v1` |

---

## 9. Pending Decisions

10 keputusan bisnis masih terbuka (PBD-01 s.d PBD-10):

| ID | Topik |
|---|---|
| PBD-01 | Formula actual hours > 8 jam |
| PBD-02 | Billing hari Minggu/libur |
| PBD-03 | Dampak hari libur terhadap buffer MOB/DEMOB |
| PBD-04 | Master data operator (tabel vs snapshot teks) |
| PBD-05 | Threshold approval refund (ADMIN vs OWNER) |
| PBD-06 | Persentase denda pembatalan pasca-bayar |
| PBD-07 | Minimum DP partial payment |
| PBD-08 | Resolusi overpayment (refund vs kredit deposit) |
| PBD-09 | Frekuensi perpanjangan deadline pembayaran |
| PBD-10 | Saluran notifikasi (WhatsApp API vs Email/In-App) |

**Tidak ada yang memblokir Phase 2.** Seluruh pending decisions terisolasi pada logika kalkulasi spesifik yang bisa di-stub/placeholder dan difinalisasi secara inkremental.

---

## 10. Risk

| Risk | Severity | Mitigasi |
|---|---|---|
| Pending pricing rules (PBD-01, PBD-02) belum final saat billing service dibangun | Medium | Stub kalkulasi dengan formula dasar (flat prorata), finalisasi sebelum UAT. |
| Shared hosting performance limit pada query availability kompleks | Low | Optimasi index, pagination, lazy-load. Profiling query di staging. |
| Database queue latency (cron per menit) vs user expectation real-time | Low | Notifikasi in-app polling setiap 30 detik. Ekspektasi user di-set bahwa notifikasi bersifat near-real-time. |
| File storage disk penuh di shared hosting | Low | Monitoring disk usage via cPanel. Kompresi gambar saat upload. Archival policy berkala. |

---

## 11. Rekomendasi untuk Phase 2

1. **Mulai dari Migration & Model:** Implementasi 29 tabel sesuai Data Dictionary (`05-data-dictionary-v3.md`). Validasi FK dan constraint di database level.
2. **Bangun Auth & RBAC terlebih dahulu:** Sanctum setup, middleware role, Policy classes untuk 3 role.
3. **Implementasi Booking Flow end-to-end:** State machine booking sebagai modul inti pertama (DRAFT -> COMPLETED).
4. **Stub Pricing Service:** Implementasi formula dasar (hourly rate * actual hours, min 8 jam flat) dengan placeholder untuk PBD-01/PBD-02.
5. **Setup Cron Scheduler:** `php artisan schedule:run` untuk auto-expire invoices dan payment reminders.
6. **Unit Test State Machine:** Validasi seluruh transisi valid dan forbidden transitions.

---

## Final Consistency Review

### Pemeriksaan Silang Antar Dokumen

| Aspek Pemeriksaan | Status | Catatan |
|---|---|---|
| Konflik status antar state machine | **CLEAR** | Booking, Rental, Equipment Unit, Invoice, Payment, Refund saling konsisten. |
| Entity tanpa relasi di ERD | **CLEAR** | Seluruh 29 entitas terhubung via FK atau polimorfik. `business_calendars` berdiri mandiri (lookup table). |
| FK yang hilang | **CLEAR** | Seluruh FK terdefinisi di Data Dictionary dengan delete behavior (CASCADE/RESTRICT). |
| API yang memungkinkan illegal transition | **CLEAR** | Setiap endpoint transisi state mencantumkan precondition status. Error code `INVALID_STATE_TRANSITION` tersedia. |
| Business rule belum diwakili database | **CLEAR** | 35 BR memiliki representasi kolom/tabel/constraint di ERD. |
| Financial history yang dapat terhapus | **CLEAR** | Snapshot price di `booking_details` dan `invoice_details`. Soft delete pada invoice. Audit log immutable. |
| Price history yang tidak aman | **CLEAR** | `equipment_price_versions` mencatat riwayat. Snapshot kolom pada transaksi kebal terhadap update master. |
| Attachment tanpa owner | **CLEAR** | Polymorphic `attachable_type` + `attachable_id` + `uploaded_by` FK memastikan setiap file memiliki pemilik dan konteks. |
| Unit assignment tanpa histori | **CLEAR** | `booking_unit_assignments.is_current` + `status` (ASSIGNED/REPLACED) menyimpan riwayat penggantian unit lengkap. |
| Timesheet revision kehilangan data lama | **CLEAR** | `timesheet_revisions` append-only dengan `version`, `old_start_hm`, `old_end_hm`, `revision_reason`, `revised_by`. |
| Dependency tidak cocok dengan cPanel | **CLEAR** | Zero Redis, Zero Docker, Zero Horizon, Zero MinIO, Zero custom Nginx, Zero Node.js server di production. |
| Infrastruktur yang tidak diperlukan | **CLEAR** | Tidak ada komponen yang melebihi kebutuhan. Arsitektur minimal dan fungsional. |

**Tidak ditemukan konflik, celah relasi, atau inkonsistensi lintas dokumen.**
