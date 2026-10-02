# FINAL BLACK-BOX REGRESSION REPORT — RAFA Rental System

> **PENTING:** Dokumen ini adalah hasil pengujian black-box MANUAL melalui browser.
> Setiap PASS harus diverifikasi tester langsung di browser (bukan oleh unit test API/backend).
> Isi semua field hasil pengujian secara objektif; jangan ubah hasil setelah workaround.

---

## 1. Informasi Pengujian

| Komponen | Detail |
|---|---|
| **Tanggal Mulai** | [YYYY-MM-DD] |
| **Tanggal Selesai** | [YYYY-MM-DD] |
| **Tester** | [Nama] |
| **Browser + Versi** | [Chrome / Edge / Firefox] |
| **Resolusi** | Desktop: 1920×1080 · Mobile: 390×844 |
| **URL Frontend** | http://localhost:5173 |
| **Branch** | fix/final-regression |
| **Acuan Flow** | Phase 1–8 yg sudah diterapkan |

### Environment Record
- DB seed terakhir: `php artisan migrate:fresh --seed` (catat tanggal & jam).
- Akun test yg dipakai: admin@rafarental.com · owner@rafarental.com · budi@kontraktor.com · siti@tambang.com · [akun baru].

---

## 2. Ringkasan Eksekutif

| Kategori | Total | PASS | FAIL | BLOCKED |
|---|---|---|---|---|
| USER | 21 | | | |
| ADMIN | 21 | | | |
| OWNER | 7 | | | |
| CROSS-ROLE | 5 | | | |
| TIMESHEET BILLING | 3 | | | |
| INVOICE RULES | 2 | | | |
| FINAL SAFETY CHECKLIST | 11 | | | |
| **TOTAL** | **70** | | | |

| Metrik | Nilai |
|---|---|
| Pass Rate | % |
| CRITICAL Bug | |
| HIGH Bug | |
| MEDIUM Bug | |
| LOW Bug | |
| **FINAL STATUS** | READY / READY WITH BUGS / NOT READY |

---

## 3. USER — FLOW (21 Skenario)

### USR-F1 Login
| Field | Nilai |
|---|---|
| TEST ID | USR-F1 |
| PRECONDITION | Akun terdaftar & aktif |
| STEPS | Buka /login → isi email+password → Masuk |
| EXPECTED | Redirect ke /app, nama tampil di navbar, sidebar USER lengkap |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F2 Verification Status
| Field | Nilai |
|---|---|
| TEST ID | USR-F2 |
| STEPS | Profil → lihat status Verifikasi Akun |
| EXPECTED | Badge "Verifikasi Akun: Terverifikasi" untuk budi; user baru "Belum Verifikasi" |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F3 Catalog
| Field | Nilai |
|---|---|
| TEST ID | USR-F3 |
| STEPS | /app/equipment → lihat kartu model |
| EXPECTED | Foto, tipe, kapasitas, tarif, + badge "X unit tersedia" dari jumlah fisik AVAILABLE |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F4 Cart Badge
| Field | Nilai |
|---|---|
| TEST ID | USR-F4 |
| STEPS | Tambahkan PC200 qty 2 + 1 model qty 1 → lihat ikon Cart di navbar |
| EXPECTED | Badge di navbar menampilkan **3** (jumlah total unit, bukan jumlah baris) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F5 Cart Checkbox
| Field | Nilai |
|---|---|
| TEST ID | USR-F5 |
| STEPS | /app/cart → centang satu item → "Lanjut ke Booking" |
| EXPECTED | Hanya item tercentang yg jadi booking; item lain tetap di cart; subtitle "Lanjut ke Booking (n)" + estimasi subtotal |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F6 Project Location
| Field | Nilai |
|---|---|
| TEST ID | USR-F6 |
| STEPS | Buka AddToCart sebagai user tanpa lokasi → pilih skema/tanggal |
| EXPECTED | Modal "Lokasi Proyek Diperlukan" + [Tambah Lokasi Proyek]→/app/locations + [Batalkan] |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F7 Recommendation
| Field | Nilai |
|---|---|
| TEST ID | USR-F7 |
| STEPS | /app/recommendations → isi galian basah, rawa, 1500m³, kedalaman 5, jangkauan 9, 14 hari |
| EXPECTED | Hasil rekomendasi tampil, tanpa error |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F8 Booking (Submit)
| Field | Nilai |
|---|---|
| TEST ID | USR-F8 |
| PRECONDITION | Verified, cart terisi + lokasi terpilih |
| STEPS | Submit booking dari cart |
| EXPECTED | Booking DRAFT→submit→PENDING_APPROVAL, muncul di /app/bookings |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F9 Approval Notification
| Field | Nilai |
|---|---|
| TEST ID | USR-F9 |
| PRECONDITION | Admin approve booking (USR-F8) |
| STEPS | Buka ikon notifikasi → klik "Booking disetujui" |
| EXPECTED | Notifikasi ada, klik → halaman **Tagihan & Bayar** (bukan dashboard umum) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F10 MOB/DEMOB Invoice
| Field | Nilai |
|---|---|
| TEST ID | USR-F10 |
| STEPS | /app/invoices setelah booking approved |
| EXPECTED | Hanya ada invoice MOB/DEMOB (jumlah mob+demob), **tanpa** tarif sewa harian |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F11 Countdown 24h
| Field | Nilai |
|---|---|
| TEST ID | USR-F11 |
| STEPS | Lihat invoice ISSUED → amati countdown |
| EXPECTED | `[ HH:MM:SS ] tersisa` + "Jatuh tempo: … WIB"; refresh tidak reset; deadline = issued_at+24h |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F12 Payment (Upload)
| Field | Nilai |
|---|---|
| TEST ID | USR-F12 |
| STEPS | Upload bukti transfer + nominal untuk MOB/DEMOB invoice |
| EXPECTED | Payment SUBMITTED, muncul di riwayat |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F13 Rental
| Field | Nilai |
|---|---|
| TEST ID | USR-F13 |
| PRECONDITION | Admin sudah dispatch/arrive/ongoing |
| STEPS | /app/rentals → lihat status ONGOING + unit |
| EXPECTED | Status benar, nama unit + operator tampil |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F14 Timesheet Read/Detail
| Field | Nilai |
|---|---|
| TEST ID | USR-F14 |
| STEPS | /app/timesheets → buka detail |
| EXPECTED | Tampil jam mulai/selesai (HH:mm), break, actual hours; **TIDAK ada** tombol input/edit/sign/user validasi |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F15 Daily Work Invoice
| Field | Nilai |
|---|---|
| TEST ID | USR-F15 |
| PRECONDITION | Timesheet sudah APPROVED oleh Admin |
| STEPS | /app/invoices → cari invoice DAILY_WORK |
| EXPECTED | Total = actual hours × tarif snapshot; timestamps benar; terbit saat timesheet disimpan |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F16 Payment (Daily Work)
| Field | Nilai |
|---|---|
| TEST ID | USR-F16 |
| STEPS | Upload bukti bayar invoice Daily Work |
| EXPECTED | Payment SUBMITTED; deadline 24h sejak issued |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F17 Outstanding
| Field | Nilai |
|---|---|
| TEST ID | USR-F17 |
| STEPS | /app/outstanding |
| EXPECTED | Hanya invoice milik akun sendiri; saldo = grand−paid; tidak bocor data user lain |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F18 Refund
| Field | Nilai |
|---|---|
| TEST ID | USR-F18 |
| STEPS | /app/refunds |
| EXPECTED | Refund tampil sesuai status; bukti transfer jika COMPLETED |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F19 Notification (Rejected Payment)
| Field | Nilai |
|---|---|
| TEST ID | USR-F19 |
| PRECONDITION | Admin reject payment |
| STEPS | Klik notifikasi "Pembayaran ditolak" |
| EXPECTED | Navigasi ke /app/invoices; badge berkurang setelah dibaca |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F20 Unverified Checkout Blocked
| Field | Nilai |
|---|---|
| TEST ID | USR-F20 |
| STEPS | Akun baru UNVERIFIED → coba checkout/booking |
| EXPECTED | Diblokir dgn pesan verifikasi akun; tetap bisa lihat katalog + rekomendasi |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### USR-F21 Logout
| Field | Nilai |
|---|---|
| TEST ID | USR-F21 |
| STEPS | Keluar → coba akses /app |
| EXPECTED | Redirect ke /login; sesi bersih |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 4. ADMIN — FLOW (21 Skenario)

### ADM-F1 Login
| Field | Nilai |
|---|---|
| TEST ID | ADM-F1 |
| STEPS | Login admin → /admin |
| EXPECTED | Redirect /admin; sidebar ADMIN; navbar tanpa menu landing |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F2 Dashboard
| Field | Nilai |
|---|---|
| TEST ID | ADM-F2 |
| STEPS | /admin → KPI + chart + ringkasan |
| EXPECTED | Data real; KPI & grafik sinkron tanggal |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F3 Account Verification
| Field | Nilai |
|---|---|
| TEST ID | ADM-F3 |
| STEPS | /admin/users → lihat akun belum terverifikasi → klik "Verifikasi Akun" |
| EXPECTED | Status jadi VERIFIED; tampil phone/identity/company; audit actor+timestamp tercatat |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F4 Master Data
| Field | Nilai |
|---|---|
| TEST ID | ADM-F4 |
| STEPS | /admin/equipment → create/edit type & model; upload foto |
| EXPECTED | CRUD berfungsi; foto persist di katalog + landing |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F5 Pricing
| Field | Nilai |
|---|---|
| TEST ID | ADM-F5 |
| STEPS | /admin/pricing → create/update tarif + overtime |
| EXPECTED | Tarif baru bisa dipakai booking baru; harga lama (snapshot) tidak berubah |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F6 Approval Booking
| Field | Nilai |
|---|---|
| TEST ID | ADM-F6 |
| STEPS | /admin/bookings → review + approve booking USER |
| EXPECTED | Status APPROVED; notifikasi user "Booking disetujui"; **auto terbit invoice MOB/DEMOB** |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F7 Unit Assignment
| Field | Nilai |
|---|---|
| TEST ID | ADM-F7 |
| STEPS | Assign unit fisik ke booking APPROVED |
| EXPECTED | Unit → ASSIGNED; tidak bisa dipakai booking lain; availability berkurang |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F8 MOB/DEMOB Invoice (Verifikasi)
| Field | Nilai |
|---|---|
| TEST ID | ADM-F8 |
| STEPS | Lihat invoice MOB/DEMOB hasil approval |
| EXPECTED | Hanya MOB+DEMOB line; grand total sesuai snapshot; status ISSUED + due 24h |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F9 Payment Verification
| Field | Nilai |
|---|---|
| TEST ID | ADM-F9 |
| STEPS | /admin/payments → buka bukti → Approve |
| EXPECTED | Payment APPROVED; invoice status update; audit tercatat |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F10 Dispatch
| Field | Nilai |
|---|---|
| TEST ID | ADM-F10 |
| STEPS | /admin/rentals → Dispatch |
| EXPECTED | Rental → DISPATCHED |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F11 Arrival
| Field | Nilai |
|---|---|
| TEST ID | ADM-F11 |
| STEPS | Confirm arrival |
| EXPECTED | Rental → ARRIVED |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F12 Ongoing
| Field | Nilai |
|---|---|
| TEST ID | ADM-F12 |
| STEPS | Start ongoing + HM awal + operator |
| EXPECTED | Rental → ONGOING |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F13 Timesheet Input
| Field | Nilai |
|---|---|
| TEST ID | ADM-F13 |
| STEPS | /admin/timesheets → Input Timesheet → jam mulai/selesai (08:00–16:30), break, operator |
| EXPECTED | Helper "Masukkan jam aktual…"; actual hours = (selesai−mulai)−break, tanpa rounding |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F14 Auto Valid (APPROVED)
| Field | Nilai |
|---|---|
| TEST ID | ADM-F14 |
| STEPS | Simpan timesheet |
| EXPECTED | **Langsung status APPROVED**; TIDAK ada step konfirmasi user / signature / validasi tambahan |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F15 Daily Work Invoice
| Field | Nilai |
|---|---|
| TEST ID | ADM-F15 |
| STEPS | Setelah save timesheet → cek invoice |
| EXPECTED | **Auto terbit invoice DAILY_WORK** utk hari tsb; total perhitungan normal+overtime benar |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F16 Payment Verification (Daily)
| Field | Nilai |
|---|---|
| TEST ID | ADM-F16 |
| STEPS | Verifikasi pembayaran invoice harian |
| EXPECTED | Approve → PAID / partial → PARTIALLY_PAID; overpayment → OVERPAID tanpa auto-refund |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F17 Return
| Field | Nilai |
|---|---|
| TEST ID | ADM-F17 |
| STEPS | Proses Return + HM akhir |
| EXPECTED | Rental → RETURNED; unit TIDAK langsung AVAILABLE (INSPECTION) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F18 Inspection
| Field | Nilai |
|---|---|
| TEST ID | ADM-F18 |
| STEPS | Isi checklist inspeksi + keputusan |
| EXPECTED | Unit → AVAILABLE/MAINTENANCE/DAMAGED sesuai keputusan |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F19 Unit Status
| Field | Nilai |
|---|---|
| TEST ID | ADM-F19 |
| STEPS | /admin/units → cek status unit pasca-inspeksi |
| EXPECTED | Status konsisten; unit siap dipakai booking baru |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F20 Notification Admin Center
| Field | Nilai |
|---|---|
| TEST ID | ADM-F20 |
| STEPS | Klik ikon bell admin → "Lihat semua" → /admin/notifications |
| EXPECTED | Center menampilkan **notification in-app** (title, message, time, unread, target); klik → halaman terkait |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### ADM-F21 Logout
| Field | Nilai |
|---|---|
| TEST ID | ADM-F21 |
| STEPS | Keluar → akses /admin |
| EXPECTED | Redirect /login |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 5. OWNER — FLOW (7 Skenario)

### OWN-F1 Login
| Field | Nilai |
|---|---|
| TEST ID | OWN-F1 |
| STEPS | Login owner → /owner |
| EXPECTED | Sidebar OWNER (Executive Summary, Laporan Pendapatan, Audit Trail, Rekening); tanpa bell |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F2 Executive Summary
| Field | Nilai |
|---|---|
| TEST ID | OWN-F2 |
| STEPS | /owner → activity ringkas |
| EXPECTED | Data real bukan placeholder; read-only |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F3 KPI
| Field | Nilai |
|---|---|
| TEST ID | OWN-F3 |
| STEPS | Periksa kartu KPI + date filter |
| EXPECTED | Angka & Rp akurat; filter ubah data |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F4 Charts
| Field | Nilai |
|---|---|
| TEST ID | OWN-F4 |
| STEPS | Amati grafik SVG + hover tooltip |
| EXPECTED | Tampil benar, tooltip presisi |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F5 Laporan Pendapatan
| Field | Nilai |
|---|---|
| TEST ID | OWN-F5 |
| STEPS | /owner/revenue → filter + detail + (export jika ada) |
| EXPECTED | Tabel revenue akurat; filter berfungsi |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F6 Audit Trail
| Field | Nilai |
|---|---|
| TEST ID | OWN-F6 |
| STEPS | /owner/audit → search/filter + detail modal |
| EXPECTED | Riwayat operasional tampil; bukan placeholder |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F7 Rekening & Kontrol
| Field | Nilai |
|---|---|
| TEST ID | OWN-F7 |
| STEPS | /owner/settings | 
| EXPECTED | Daftar rekening transparan; owner read-only (mutasi ditolak) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### OWN-F8 Logout
| Field | Nilai |
|---|---|
| TEST ID | OWN-F8 |
| STEPS | Keluar |
| EXPECTED | Redirect /login |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 6. CRITICAL CROSS-ROLE (5 Skenario)

### XR-F1 User A cart → User B approved → Unavailable
| Field | Nilai |
|---|---|
| TEST ID | XR-F1 |
| PRECONDITION | 1 unit fisik; User A & B pilih periode sama; A punya cart |
| STEPS | 1) A tambah cart 2) B submit+approve + assign unit 2–6 3) A "Cek Ketersediaan" / recheck |
| EXPECTED | Cart A tampil "Unit tidak tersedia untuk periode ini…"; checkout A disabled |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### XR-F2 User B tidak bayar 24h → expired
| Field | Nilai |
|---|---|
| TEST ID | XR-F2 |
| PRECONDITION | XR-F1 terjadi; invoice B melewati due_at 24h |
| STEPS | Jalankan scheduler `php artisan bookings:expire` / tunggu |
| EXPECTED | Booking B → EXPIRED; unit → AVAILABLE; notifikasi expired |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### XR-F3 Slot release → User A available
| Field | Nilai |
|---|---|
| TEST ID | XR-F3 |
| STEPS | A recheck setelah expired |
| EXPECTED | Availability kembali; A bisa submit booking |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### XR-F4 Satu kondisi double-booking dicegah
| Field | Nilai |
|---|---|
| TEST ID | XR-F4 |
| STEPS | 2 booking overlap pada unit sama; coba assign unit sama |
| EXPECTED | Unit kedua ditolak conflict; tidak ada double booking |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### XR-F5 Data lintas role sinkron
| Field | Nilai |
|---|---|
| TEST ID | XR-F5 |
| STEPS | Jalankan 1 transaksi penuh; cek data di User → Admin → Owner |
| EXPECTED | Data yang dibuat satu role muncul benar di role lain (booking, invoice, payment, timesheet) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 7. TIMESHEET BILLING (3 Skenario)

### TS-F1 7 jam → normal
| Field | Nilai |
|---|---|
| TEST ID | TS-F1 |
| INPUT | start 08:00 end 15:00 break 0 → 7h |
| EXPECTED | Daily invoice = 7 × tarif normal (tanpa overtime) |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### TS-F2 8 jam → normal
| Field | Nilai |
|---|---|
| TEST ID | TS-F2 |
| INPUT | start 08:00 end 16:30 break 30m → 8h |
| EXPECTED | Daily invoice = 8 × tarif normal |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### TS-F3 10 jam → 8 normal + 2 overtime
| Field | Nilai |
|---|---|
| TEST ID | TS-F3 |
| INPUT | start 08:00 end 18:00 break 0 → 10h; overtime_rate dari master |
| EXPECTED | Invoice punya 2 line: 8h × normal + 2h × overtime; tanpa hardcode |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 8. INVOICE RULES (2 Skenario)

### INV-F1 Booking approved → ONLY MOB/DEMOB
| Field | Nilai |
|---|---|
| TEST ID | INV-F1 |
| STEPS | Approve booking → cek seluruh invoice booking tsb |
| EXPECTED | `invoice_type = MOB_DEMOB`; TIDAK ada DAILY_WORK / RENTAL_PREPAYMENT; tidak duplicate |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

### INV-F2 Timesheet saved → Daily Work Invoice
| Field | Nilai |
|---|---|
| TEST ID | INV-F2 |
| STEPS | Simpan timesheet → cek invoice |
| EXPECTED | 1 invoice DAILY_WORK per hari per unit; tidak duplicate; 1 invoice ← 1 timesheet |
| ACTUAL | |
| PASS/FAIL/BUG/SEV | |
| EVIDENCE | |

---

## 9. FINAL SAFETY CHECKLIST (11 Item)

| # | Item | PASS/FAIL | Catatan |
|---|---|---|---|
| SAFE-1 | Tidak ada double booking | | |
| SAFE-2 | Tidak ada duplicate invoice | | |
| SAFE-3 | Price scheme (All-in / Non All-in) benar per line | | |
| SAFE-4 | Countdown benar & tidak reset saat refresh/reject | | |
| SAFE-5 | Availability akurat (tidak false available/unavailable) | | |
| SAFE-6 | Tidak ada false notification / dead notification | | |
| SAFE-7 | Unauthorized booking diblokir (unverified, role, IDOR) | | |
| SAFE-8 | Billing benar (normal, overtime, fixed snapshot price) | | |
| SAFE-9 | Tidak ada infinite loading / infinite skeleton | | |
| SAFE-10 | Tidak ada data hilang setelah refresh / navigation | | |
| SAFE-11 | Payment reject → re-upload; deadline tetap; overpay tidak auto-refund | | |

---

## 10. Bug Log

| Bug ID | Test ID | Severity | Module | Expected | Actual | Reproduce | Evidence | Status |
|---|---|---|---|---|---|---|---|---|
| BUG-001 | | | | | | | | Open/Fixed |
| BUG-002 | | | | | | | | Open/Fixed |
| BUG-003 | | | | | | | | Open/Fixed |
| BUG-004 | | | | | | | | Open/Fixed |
| BUG-005 | | | | | | | | Open/Fixed |

**Distribusi Severity:** CRITICAL: ___ · HIGH: ___ · MEDIUM: ___ · LOW: ___

---

## 11. FINAL STATUS

**[ ] READY** — Semua test PASS, 0 CRITICAL, 0 HIGH.

**[ ] READY WITH BUGS** — Mayoritas PASS; hanya bug MEDIUM/LOW terdokumentasi.

**[ ] NOT READY** — Terdapat bug CRITICAL/HIGH blocker.

### Catatan Penutup
- [Catatan bug blocker / workaround yang TIDAK boleh mengubah hasil]
- [Screenshot terlampir di lokasi: docs/testing/screenshots/…]

---

*Laporan ini adalah template hasil black-box regression final. Isi semua field + lampirkan screenshot setelah sesi manual browser selesai.*