# MANUAL BLACK-BOX TEST RESULT — RAFA Rental System

> **PENTING:** Dokumen ini diisi oleh tester manusia setelah pengujian manual di browser selesai dilaksanakan. Jangan menyatakan PASS berdasarkan unit test atau kode sumber. PASS hanya jika tester berhasil menjalankan langkah tersebut di browser dan hasilnya sesuai expected.

---

## Informasi Pelaksanaan Pengujian

| Komponen | Detail |
|---|---|
| **Tanggal Mulai** | [YYYY-MM-DD] |
| **Tanggal Selesai** | [YYYY-MM-DD] |
| **Nama Tester** | [Nama Lengkap] |
| **Browser & Versi** | [Chrome 131 / Edge 131 / Firefox 133] |
| **OS Tester** | [Windows 11 / macOS Sonoma / Ubuntu 24.04] |
| **Resolusi Desktop** | 1920 x 1080 |
| **Resolusi Mobile** | 390 x 844 (iPhone 14 viewport) |
| **Acuan Test Plan** | `docs/testing/manual-blackbox-test-plan.md` |

---

## Ringkasan Hasil Pengujian (Executive Summary)

### Per Kategori Role

| Kategori | Total Test Case | PASS | FAIL | BLOCKED |
|---|---|---|---|---|
| **B. USER** | 33 | | | |
| **C. ADMIN** | 33 | | | |
| **D. OWNER** | 15 | | | |
| **E. CROSS-ROLE** | 1 | | | |
| **Subtotal Fungsional** | **82** | | | |

### Per Kategori Non-Fungsional

| Kategori | Total Item | PASS | FAIL |
|---|---|---|---|
| **F. NEGATIVE TESTING** | 15 | | |
| **G. UX BLACK-BOX** | 15 | | |
| **H. BUSINESS RULE** | 23 | | |
| **Subtotal Non-Fungsional** | **53** | | |

### Grand Total

| Metrik | Nilai |
|---|---|
| **TOTAL TEST ITEMS** | **135** |
| **TOTAL PASS** | |
| **TOTAL FAIL** | |
| **TOTAL BLOCKED** | |
| **Pass Rate (%)** | |

---

## Hasil Detail per Modul

### B. USER — End-to-End (33 Langkah)

| Test ID | Scenario | PASS/FAIL | Bug ID | Severity | Notes |
|---|---|---|---|---|---|
| USR-01 | Register akun baru | | | | |
| USR-02 | Login customer | | | | |
| USR-03 | Logout & login ulang | | | | |
| USR-04 | Profile update & persist | | | | |
| USR-05 | Katalog equipment grid | | | | |
| USR-06 | Search & filter katalog | | | | |
| USR-07 | Detail spesifikasi alat | | | | |
| USR-08 | Recommendation engine | | | | |
| USR-09 | Cart add & manage | | | | |
| USR-10 | Project location list | | | | |
| USR-11 | CRUD project location | | | | |
| USR-12 | Pilih tanggal/availability | | | | |
| USR-13 | Submit booking | | | | |
| USR-14 | Lihat status booking | | | | |
| USR-15 | Lihat approval result | | | | |
| USR-16 | Lihat assigned unit | | | | |
| USR-17 | Lihat invoice | | | | |
| USR-18 | Lihat payment deadline | | | | |
| USR-19 | Upload payment proof | | | | |
| USR-20 | Lihat payment status | | | | |
| USR-21 | Re-upload jika rejected | | | | |
| USR-22 | Partial payment | | | | |
| USR-23 | Lihat rental | | | | |
| USR-24 | Lihat timesheet | | | | |
| USR-25 | Confirm/sign timesheet | | | | |
| USR-26 | Lihat daily work invoice | | | | |
| USR-27 | Upload payment daily work | | | | |
| USR-28 | Lihat payment history | | | | |
| USR-29 | Lihat outstanding | | | | |
| USR-30 | Lihat refund | | | | |
| USR-31 | Notification | | | | |
| USR-32 | Profile & password | | | | |
| USR-33 | Logout akhir | | | | |

### C. ADMIN — End-to-End (33 Langkah)

| Test ID | Scenario | PASS/FAIL | Bug ID | Severity | Notes |
|---|---|---|---|---|---|
| ADM-01 | Login admin | | | | |
| ADM-02 | Equipment Type CRUD | | | | |
| ADM-03 | Equipment Model CRUD | | | | |
| ADM-04 | Equipment Unit CRUD | | | | |
| ADM-05 | Upload/replace photo | | | | |
| ADM-06 | Pricing CRUD | | | | |
| ADM-07 | Bank Account CRUD | | | | |
| ADM-08 | Lihat booking masuk | | | | |
| ADM-09 | Review booking | | | | |
| ADM-10 | Approve/reject booking | | | | |
| ADM-11 | Assign physical unit | | | | |
| ADM-12 | Replace unit | | | | |
| ADM-13 | Dispatch | | | | |
| ADM-14 | Confirm arrival | | | | |
| ADM-15 | Start/confirm ongoing | | | | |
| ADM-16 | Input timesheet | | | | |
| ADM-17 | Edit/correction timesheet | | | | |
| ADM-18 | Revision history | | | | |
| ADM-19 | Validate/reject timesheet | | | | |
| ADM-20 | Create/issue invoice | | | | |
| ADM-21 | Review payment proof | | | | |
| ADM-22 | Approve/reject payment | | | | |
| ADM-23 | Partial payment verify | | | | |
| ADM-24 | Overpayment verify | | | | |
| ADM-25 | Outstanding monitoring | | | | |
| ADM-26 | Refund process | | | | |
| ADM-27 | Return demobilization | | | | |
| ADM-28 | Inspection & set status | | | | |
| ADM-29 | Notification admin | | | | |
| ADM-30 | Reports operational/financial | | | | |
| ADM-31 | Dashboard KPI & charts | | | | |
| ADM-32 | CMS landing page | | | | |
| ADM-33 | Logout admin | | | | |

### D. OWNER — End-to-End (15 Langkah)

| Test ID | Scenario | PASS/FAIL | Bug ID | Severity | Notes |
|---|---|---|---|---|---|
| OWN-01 | Executive Summary login | | | | |
| OWN-02 | Date filter | | | | |
| OWN-03 | KPI cards | | | | |
| OWN-04 | Charts/trends | | | | |
| OWN-05 | Data summary | | | | |
| OWN-06 | Laporan pendapatan | | | | |
| OWN-07 | Search/filter revenue | | | | |
| OWN-08 | Detail revenue | | | | |
| OWN-09 | Export laporan | | | | |
| OWN-10 | Audit trail logs | | | | |
| OWN-11 | Search/filter audit | | | | |
| OWN-12 | Detail audit modal | | | | |
| OWN-13 | Rekening & kontrol | | | | |
| OWN-14 | Owner full read access | | | | |
| OWN-15 | Owner cannot mutate | | | | |

### E. CROSS-ROLE — Full Lifecycle (1 Test Case)

| Test ID | Scenario | PASS/FAIL | Bug ID | Severity | Notes |
|---|---|---|---|---|---|
| CROSS-01 | Full rental lifecycle E2E (10 steps) | | | | |

### F. NEGATIVE TESTING (15 Kasus)

| Test ID | Scenario | PASS/FAIL | Bug ID | Severity | Notes |
|---|---|---|---|---|---|
| NEG-01 | Login password salah | | | | |
| NEG-02 | Register email duplicate | | | | |
| NEG-03 | Field wajib kosong | | | | |
| NEG-04 | Format input salah | | | | |
| NEG-05 | IDOR akses booking lain | | | | |
| NEG-06 | User akses /admin | | | | |
| NEG-07 | Double submit booking | | | | |
| NEG-08 | Upload .exe payment | | | | |
| NEG-09 | Upload > 5MB payment | | | | |
| NEG-10 | Payment nominal 0 | | | | |
| NEG-11 | Booking tanggal lalu | | | | |
| NEG-12 | Assign unit conflict | | | | |
| NEG-13 | Invalid status transition | | | | |
| NEG-14 | User input timesheet | | | | |
| NEG-15 | Owner mutasi pricing | | | | |

### G. UX BLACK-BOX (15 Item)

| Check ID | Komponen | PASS/FAIL | Notes |
|---|---|---|---|
| UX-01 | Loading skeleton | | |
| UX-02 | Anti-infinite loading | | |
| UX-03 | Empty state | | |
| UX-04 | Error state | | |
| UX-05 | Toast notification | | |
| UX-06 | Anti-false notification | | |
| UX-07 | Modal/dialogs | | |
| UX-08 | Button states | | |
| UX-09 | Form validation feedback | | |
| UX-10 | Sidebar navigation | | |
| UX-11 | Browser back/forward | | |
| UX-12 | Page refresh persistence | | |
| UX-13 | Mobile responsive | | |
| UX-14 | Mobile drawer nav | | |
| UX-15 | Role accent mapping | | |

### H. BUSINESS RULE (23 Aturan)

| Rule ID | Aturan Bisnis | PASS/FAIL | Notes |
|---|---|---|---|
| BR-01 | Booking workflow status | | |
| BR-02 | 1 booking = 1 location | | |
| BR-03 | Model vs unit separation | | |
| BR-04 | Availability pre-check | | |
| BR-05 | Dispatch ≠ Ongoing | | |
| BR-06 | Admin confirms arrival | | |
| BR-07 | Timesheet from actual work | | |
| BR-08 | Admin input/validate TS | | |
| BR-09 | User confirm/sign TS | | |
| BR-10 | Actual hours = billing | | |
| BR-11 | No rounding | | |
| BR-12 | No overtime tariff | | |
| BR-13 | All-in vs Non All-in | | |
| BR-14 | MOB/DEMOB per unit | | |
| BR-15 | Historical price immutable | | |
| BR-16 | 1 invoice → N payments | | |
| BR-17 | 1 payment → 1 invoice | | |
| BR-18 | Re-upload rejected payment | | |
| BR-19 | Deadline fixed 24h | | |
| BR-20 | Overpay no auto-refund | | |
| BR-21 | Unit non-available after return | | |
| BR-22 | Inspection = readiness | | |
| BR-23 | Audit history immutable | | |

---

## Bug Log

| Bug ID | Test ID | Severity | Module | Description | Reproduce Steps | Expected | Actual | Status |
|---|---|---|---|---|---|---|---|---|
| BUG-001 | | | | | | | | Open |
| BUG-002 | | | | | | | | Open |
| BUG-003 | | | | | | | | Open |
| BUG-004 | | | | | | | | Open |
| BUG-005 | | | | | | | | Open |

---

## Distribusi Severity Bug

| Severity | Count |
|---|---|
| **CRITICAL** | |
| **HIGH** | |
| **MEDIUM** | |
| **LOW** | |
| **TOTAL BUGS** | |

---

## Final Verdict

### Status Kesiapan Sistem

| Kriteria | Syarat | Terpenuhi? |
|---|---|---|
| Zero CRITICAL bugs | Tidak ada bug CRITICAL yang terbuka | [ ] Ya / [ ] Tidak |
| Zero HIGH bugs | Tidak ada bug HIGH yang terbuka | [ ] Ya / [ ] Tidak |
| Pass Rate ≥ 95% | Persentase kelulusan test ≥ 95% | [ ] Ya / [ ] Tidak |
| Cross-Role E2E PASS | Alur multi-role penuh berhasil | [ ] Ya / [ ] Tidak |
| Business Rules PASS | Seluruh aturan bisnis tervalidasi | [ ] Ya / [ ] Tidak |

### Keputusan Akhir

**[ ] READY** — Seluruh test PASS, 0 CRITICAL, 0 HIGH. Sistem siap rilis.

**[ ] READY WITH BUGS** — Test sebagian besar PASS. Hanya terdapat bug MEDIUM/LOW yang terdokumentasi dan tidak mengganggu alur utama.

**[ ] NOT READY** — Masih terdapat bug CRITICAL atau HIGH yang memblokir alur utama (blocker). Perlu siklus perbaikan dan pengujian ulang.

---

*Dokumen ini disiapkan sebagai template hasil pengujian black-box manual. Setiap field wajib diisi oleh tester setelah sesi pengujian dilakukan secara langsung melalui browser.*
