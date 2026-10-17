# Diagram TA RAFA Rental — Revisi Poin 4

Kumpulan diagram Mermaid yang dapat diedit dan dipindahkan ke laporan TA. Semua diagram disusun dari **implementasi aktual** (route, policy, gate, state machine, action), bukan dari asumsi dokumentasi. Tidak ada perubahan workflow pada tahap ini.

## Daftar file

| File | Isi |
|---|---|
| [`use-case.md`](use-case.md) | Use Case Diagram 5 aktor (USER, SUPERVISOR, ADMIN, OPERATOR, OWNER) + pemetaan implementasi |
| [`activity-booking-to-rental.md`](activity-booking-to-rental.md) | Activity Diagram booking → validasi → approval → pembayaran → penugasan → rental → Timesheet → inspeksi |
| [`activity-supervisor-validation.md`](activity-supervisor-validation.md) | Activity Diagram khusus validasi/kembalikan booking oleh Supervisor |
| [`activity-operator-assignment.md`](activity-operator-assignment.md) | Activity Diagram penugasan, pergantian, pengakhiran, dan akses Operator |

## Cara render
- GitHub/GitLab: buka file `.md` (Mermaid dirender otomatis).
- VS Code: ekstensi Markdown Preview Mermaid Support.
- PlantUML tidak dipakai; seluruh diagram menggunakan sintaks Mermaid (`flowchart`) agar cukup satu toolchain.

## Aktor dan sumber otorisasi
- **USER** — default factory role; akses data milik sendiri (booking, cart, project location, invoice, payment, refund, timesheet read-only, notifikasi).
- **SUPERVISOR** — `role:SUPERVISOR`, `BookingPolicy::validate`, `BookingPolicy::view` hanya `PENDING_APPROVAL`.
- **ADMIN** — `role:ADMIN` atau `role:ADMIN,OWNER`; approval booking wajib `supervisor_decision=VALIDATED`; unit, Operator, rental, Timesheet, invoice, payment, refund, master data, CMS, user role.
- **OPERATOR** — `role:OPERATOR`, `RentalOperatorAssignmentPolicy::view` hanya assignment miliknya yang `ACTIVE`; read-only.
- **OWNER** — gate monitoring (`view-owner-dashboard`, `view-revenue-reports`, `view-audit-logs`), `approve-refund`, `deactivate-user`; **masih memiliki** `approve-booking` existing (lihat ketidaksesuaian).

## Aturan bisnis yang harus konsisten di laporan
1. **MOB/DEMOB**: `MOB = jarak rute satu arah × tarif/km`, `DEMOB = jarak rute satu arah × tarif/km`, `Total = MOB + DEMOB`. Nilai disimpan sebagai snapshot pada booking detail.
2. **Tarif rental per jam** hanya dihitung dari **Timesheet aktual** untuk **Daily Work Invoice** (1–8 jam normal, >8 jam overtime, tanpa pembulatan). Booking awal tidak memuat tarif sewa.
3. **Rekomendasi alat**: weighted rule-based multi-criteria, bobot `40% capacity_match + 30% terrain_suitability + 20% project_suitability + 10% price_suitability`; normalisasi bobot, threshold 50, maksimum 5 hasil, tie-break deterministik (skor → tarif terendah → model ID). Ketersediaan unit adalah hard exclusion.
4. **`depth_requirement` & `reach_requirement` bukan kriteria scoring** (tidak ada bobot di `config/recommendation.php` dan tidak ada di matriks Phase 7B). Di reasoning ditandai “tidak dihitung dalam skor”.
5. **Routing Mapbox belum tervalidasi live** (token & data pool belum tersedia). Diagram hanya boleh menggambarkan prasyarat konfigurasi, bukan klaim hasil uji lapangan.

## Validasi ketidaksesuaian (WAJIB dibaca sebelum diagram final)

| # | Temuan | Bukti implementasi | Dampak pada diagram | Rekomendasi |
|---|---|---|---|---|
| 1 | Workflow yang disepakati: Supervisor validasi → **Admin** persetujuan akhir. Permission existing: **OWNER juga bisa approve/reject** booking. | `AppServiceProvider`: `Gate 'approve-booking' = ADMIN,OWNER`; `BookingPolicy::approve/reject` = `ADMIN,OWNER`; route `/bookings/{booking}/approve|reject` berada di group `role:ADMIN,OWNER`. Gate `VALIDATED` tetap berlaku untuk Owner. | Use case menampilkan `OWNER --> Setujui booking` dengan catatan “existing”, agar tidak menyembunyikan permission. | **Keputusan bisnis diperlukan**: (a) pertahankan Owner sebagai approver cadangan (status quo), atau (b) batasi approval ke ADMIN saja (ubah policy + route + test). Belum diubah pada revisi ini. |
| 2 | Owner tidak boleh otomatis mendapat hak operasional baru. | Owner tidak ada di route penugasan unit/Operator, Timesheet, inspeksi, atau verifikasi pembayaran. | Use case Owner hanya monitoring + approval existing + refund/deactivate. | Tidak ada perubahan. |
| 3 | Mapbox belum live-tested. | `.env` token kosong/placeholder; `equipment_pools=0`; `distance_rate_per_km=0` pada audit terakhir. | Activity diagram menandai MOB/DEMOB sebagai snapshot dari routing yang harus dikonfigurasi. | Selesaikan konfigurasi token + pool + tarif sebelum mengklaim validasi live. |
| 4 | Supervisor bukan approver, Operator bukan input Timesheet. | `BookingPolicy::approve` tidak memuat SUPERVISOR; `TimesheetPolicy::create/validate` ADMIN only. | Use case memisahkan tegas. | Tidak ada perubahan. |

## Matriks kesesuaian diagram ↔ workflow & permission aktual

| Alur / hak akses | Diagram | Implementasi | Status |
|---|---|---|---|
| User registrasi/login, katalog, rekomendasi, lokasi, cart, booking, pembayaran, tagihan, Timesheet read-only, notifikasi | `use-case.md`, activity utama | Route + policy existing | Sesuai |
| Supervisor lihat antrean, validasi, kembalikan + catatan | `use-case.md`, `activity-supervisor-validation.md` | `SupervisorBookingController`, `ValidateBookingAction`, `ReturnBookingAction` | Sesuai |
| Perbaikan & pengajuan ulang | activity utama + supervisor | `UpdateBookingAction`, `SubmitBookingAction` (reset decision) | Sesuai |
| Admin approval/reject hanya setelah VALIDATED | activity utama | `ApproveBookingAction`/`RejectBookingAction` cek `supervisor_decision` + `supervisor_validated_at` | Sesuai |
| Owner approval booking existing | `use-case.md` (catatan) | Gate/policy/route `ADMIN,OWNER` | **Tidak sesuai dengan narasi “Admin final approval”** — butuh keputusan |
| Invoice MOB/DEMOB saat APPROVED | activity utama | `ApproveBookingAction::createMobDemobInvoice` | Sesuai |
| Pembayaran → verifikasi Admin → CONFIRMED | activity utama | `SubmitPaymentAction`, `VerifyPaymentAction` | Sesuai |
| Unit & Operator setelah pembayaran terverifikasi | activity utama + operator | `AssignBookingUnitsAction`, `AssignRentalOperatorAction` | Sesuai |
| Dispatch → Arrival → Ongoing → Return → Inspeksi | activity utama | `TransitionRentalAction`, `InspectRentalAction` | Sesuai |
| Timesheet Admin → Daily Work Invoice | activity utama | `CreateTimesheetAction` (langsung APPROVED + auto invoice) | Sesuai |
| Operator hanya akses assignment aktif miliknya | `activity-operator-assignment.md` | Middleware role + policy + scoping query | Sesuai |
| Rekomendasi weighted 40/30/20/10 | README bagian aturan | `config/recommendation.php`, `RecommendationScoringService` | Sesuai |
| Mapbox live | activity utama | Belum tervalidasi | **Blocker dokumentasi** (jangan klaim tervalidasi) |

## Keputusan yang masih diperlukan
1. **Owner approval booking**: pertahankan atau batasi ke Admin? (Jangan ubah diam-diam; diagram memakai status quo.)
2. **Mapbox**: konfirmasi token, pool armada, dan tarif/km untuk live test sebelum laporan menyatakan fitur tervalidasi.
3. **Depth/reach**: konfirmasi apakah laporan TA memang tidak menjadikannya kriteria berbobot (implementasi saat ini: bukan kriteria).
4. **Threshold rekomendasi (50) dan maksimum 5 hasil**: konfirmasi kesesuaian dengan laporan TA bila berbeda.

## Batasan revisi ini
- Tidak ada perubahan kode aplikasi, database, workflow, Timesheet, billing, atau permission.
- Tidak ada reset database/seeder destruktif.
- Diagram ini adalah draft siap tinjau, bukan klaim final akademik sampai keputusan pada bagian sebelumnya disetujui.
