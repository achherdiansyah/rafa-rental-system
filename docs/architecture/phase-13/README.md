# Phase 13: QA & Security Readiness — RAFA Rental System

Dokumentasi jaminan kualitas menyeluruh dan kesiapan keamanan produksi.

---

## 1. Tujuan Phase 13

Menjamin seluruh fitur Phase 1–12 tetap konsisten (regression), menutup
kesenjangan coverage aturan bisnis & authorization, lalu menyiapkan produksi
(security, skrip deploy, dokumentasi operasional).

---

## 2. Struktur Subphase

| Subphase | Fokus | Status |
|---|---|---|
| **13A** | Full Regression & Test Coverage (semua suite + matriks peran + golden path) | Selesai |
| **13B** | Security Review & Hardening (token expiry, rate limits, error masking, upload throttle) | Selesai |
| **13C** | Database & Performance Optimization (index jalur panas, N+1 guard, bundle review) | Selesai |
| **13D** | File Storage & Data Integrity (private/authorized download, orphan audit, FK/unik, backup-restore) | Selesai |
| **13E** | Scheduler, Queue & Operational Reliability (cron shared hosting, idempotensi, logging) | Selesai |
| **13F** | cPanel Production Readiness (CORS, trust proxy, .env production template, checklist ops) | Selesai |
| **13G** | UAT & Final Regression (journey penuh via API + auth/business-rules/error checks) | Selesai (`2ae5e1d`) |
| **13H** | Final Review, Quality Gate & Git Merge ke `main` | Selesai (merge), READY FOR PHASE 14 |

---

## 4. Final Review 13A–13G (Phase 13H)

| Checklist | Status | Bukti |
|---|---|---|
| Tidak ada critical/high defect | ✔ | UAT + semua suite hijau; 452 backend / 121 frontend |
| Production config aman | ✔ | `.env.production.example` (debug=false, key kosong, log daily); CORS env-driven; trust proxy |
| Financial/audit history immutable | ✔ | FK RESTRICT, SoftDeletes, tanpa endpoint delete, laporan non-mutasi |
| Authorization konsisten | ✔ | matriks gate per role + policy boundary teruji |
| Performance | ✔ | index jalur panas, guard N+1 |
| File storage | ✔ | privat + authorized download, orphan audit, backup/restore |
| Scheduler/queue | ✔ | cron shared host, idempotensi, job fail-fast anti-duplikasi |
| cPanel readiness | ✔ | checklist operasional lengkap (deploy manual utk operator) |
| Tidak ada dependency produksi tak perlu | ✔ | `require`: laravel/framework, sanctum, tinker |
| Dokumentasi deployment lengkap | ✔ | `docs/deployment/cpanel-production-checklist.md` |
| Tanpa Docker/Node runtime/Redis | ✔ | file cache/session, queue database, storage lokal; Node hanya saat build |

### Quality Gate 13H

| Command | Hasil |
|---|---|
| `php artisan test` | **452 passed (2526 assertions)** |
| `./vendor/bin/pint --test` | passed |
| `npm run test` | **121 passed (30 files)** |
| `npm run build` (tsc -b + vite) | sukses |
| Static analysis (larastan/phpstan) | tidak tersedia di repo |

---

## 3. Daftar Dokumen

- `01-regression-coverage.md`: Laporan regression Phase 1–12, matriks
  permission per role, boundary policy finansial, golden path
  booking→rental→timesheet→invoice→payment (433 backend tests, 121 frontend).
- `02-security-hardening.md`: Audit & perbaikan keamanan — token expiry 7 hari,
  rate limit API/auth/upload, error production tanpa stack trace, verifikasi
  mass assignment/IDOR/upload privat (439 backend tests).
- `03-database-performance.md`: Optimasi DB — index jalur panas (status/date),
  guard N+1 pada list rental, konfirmasi jalur baca non-mutasi, review bundle
  frontend (442 backend tests).
- `04-file-storage-integrity.md`: Audit storage — bukti privat + download
  berotorisasi, nama file acak, validasi mime/ukuran, audit orphan tanpa
  penghapusan otomatis, integritas FK/unik, dan strategi backup/restore
  (`db:backup` + mysql restore + rsync storage).
- `05-scheduler-queue-reliability.md`: Scheduler & reliability — jadwal cron
  shared hosting, idempotensi expiry booking/invoice, guard reminder per hari,
  job queue fail-fast tanpa duplikasi, logging failure.
- `06-cpanel-readiness.md` → `docs/deployment/cpanel-production-checklist.md`:
  checklist kesiapan cPanel — docroot, .env production, APP_KEY/debug, storage
  link, migration, cron scheduler/queue shared-host, config/route cache, CORS,
  HTTPS/trust proxy, verifikasi pasca-deploy. Tanpa Docker/Node runtime/Redis.
- `06-uat-regression.md`: UAT & final regression — journey pelanggan penuh
  (register→project/cart/booking→approve/assign→rental→timesheet→invoice→
  payment part/full/overpay→refund→notif→report/export) + auth/business-rule/
  error checks (452 backend tests, 121 frontend).