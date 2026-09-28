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
| **13C** | Database & Performance Optimization (index jalur panas, N+1 guard, bundle review) | Selesai (Aktif) |
| 13D | Deployment & Operations (deploy blueprint, backup, monitoring) | Pending |
| 13E | Final QA Gate & Git Merge | Pending |

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