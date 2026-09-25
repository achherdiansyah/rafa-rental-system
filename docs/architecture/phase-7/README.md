# Phase 7: Intelligent Decision Support & Recommendation System — RAFA Rental System

Dokumentasi arsitektur komprehensif, implementasi teknis, algoritma penilaian berbobot (*Weighted Rule-Based Scoring*), integrasi ketersediaan armada (*Availability-Aware*), dan verifikasi kualitas untuk Modul Sistem Rekomendasi Alat Berat pada RAFA Rental System.

---

## 1. Tujuan Phase 7

Membangun modul **Recommendation System** sebagai *Decision Support Tool* bagi pelanggan penyewa dan staf operasional dalam menentukan tipe dan model armada alat berat yang paling presisi berdasarkan kriteria teknis lapangan, beban target, volume kerja, dan karakteristik tanah/medan.

Prinsip Utama:
- **Decision Support Only:** Memberikan panduan dan rekomendasi kecocokan teknis; bukan merupakan jaminan keberhasilan proyek atau pemesanan otomatis.
- **Rule-Based Recommendation & Weighted Scoring:** Menghitung skor kecocokan armada berdasarkan formula multi-kriteria yang terkonfigurasi secara transparan.
- **Strictly In-App (Native PHP/Laravel):** Bebas dari dependensi Python, FastAPI, Machine Learning black-box, atau external AI APIs demi stabilitas dan kompatibilitas shared hosting / cPanel.
- **No Automatic Booking / Side Effect:** Rekomendasi tidak pernah secara otomatis membuat pesanan sewa (*booking*), reservasi unit, keranjang, ataupun invoice.
- **Availability-Aware:** Mempertimbangkan ketersediaan kuota unit fisik bebas pada rentang tanggal sewa yang diminta (*single source of truth* database).

---

## 2. Struktur Subphase

| Subphase | Fokus Modul | Status |
|---|---|---|
| **7A** | Domain Foundation, Entity Relasi, Input Kriteria, & Scoring Engine | Selesai (`1d757b6`) |
| **7B** | Implementasi Bobot & Aturan Evaluasi Detail Multi-Kriteria | Selesai (`1647307`) |
| **7C** | REST API Endpoints, Sanitasi Response, Paginasi, & Otorisasi RBAC | Selesai (`978f476`) |
| **7D** | Antarmuka Rekomendasi Pelanggan, Wizard Input, & Integrasi Katalog | Selesai (`646abc7`) |
| **7E** | Integrasi Ketersediaan Armada (*Availability-Aware Recommendations*) | Selesai (`ee99832`) |
| **7F** | Pengujian Integrasi Menyeluruh, Skenario Bisnis Kritis, & Quality Gate | Selesai (`3fae829`) |
| **7G** | Final Architecture Review, Dokumentasi Sign-off, & Git Merge | Selesai (Aktif) |

---

## 3. Recommendation Domain & Database Architecture

Struktur relasi data mematuhi normalisasi ERD V3 Phase 1:

```text
[User]
  │ 1:N
  ▼
[RecommendationRequest] (status: PENDING | PROCESSED | FAILED)
  ├── 1:1 ── [RecommendationCriteria] (Medan, Kapasitas, Volume, Jangkauan, Periode)
  └── 1:N ── [RecommendationResult] (Skor Kecocokan, Peringkat, Reasoning Text)
               │
               ▼ BelongsTo
        [EquipmentModel] (Master Data Phase 6)
```

- **`recommendation_requests`:** Menyimpan id sesi permintaan, referensi `user_id`, dan status pemrosesan.
- **`recommendation_criteria`:** Menyimpan input parameter lapangan (`project_type`, `terrain_condition`, `load_capacity`, `work_volume`, `depth_requirement`, `reach_requirement`, `start_date`, `end_date`, `duration_days`, `budget_range`).
- **`recommendation_results`:** Menyimpan hasil rekomendasi (`equipment_model_id`, `match_score`, `reasoning_text`, urutan peringkat `rank`).

---

## 4. Scoring Engine & Algoritma Penilaian

Mesin penilaian berada di domain service terisolasi `RecommendationScoringService`:
- **Formula Terbobot:** $\text{Final Score} = \sum (\text{Raw Score}_i \times \text{Normalized Weight}_i)$.
- **Sub-Kriteria Default (`config/recommendation.php`):**
  1. `CAPACITY_MATCH` (**40%**): Rasio kapasitas armada terhadap beban target.
  2. `TERRAIN_SUITABILITY` (**30%**): Kecocokan sistem penggerak roda/track dengan tanah/medan.
  3. `PROJECT_SUITABILITY` (**20%**): Relevansi fungsional tipe alat berat dengan jenis pekerjaan.
  4. `PRICE_SUITABILITY` (**10%**): Kesiapan komersial master tarif sewa aktif.
- **Tie-Breaking Deterministik:** Urutan hasil disusun berdasarkan: 1) Skor Tertinggi, 2) Tarif Sewa Terendah, 3) ID Model Unik.
- **Reasoning Traceability:** Teks penjelasan dirangkai secara akurat sesuai spesifikasi model dan kriteria input nyata.

---

## 5. REST API Architecture

- **`POST /api/v1/recommendations` & `/request`:** Validasi input melalui `StoreRecommendationRequest`, kalkulasi sinkron, dan pengembalian respon `201 Created`.
- **`GET /api/v1/recommendations`:** Riwayat permintaan terpaginasi (milik sendiri bagi `USER`, seluruh data bagi `ADMIN`/`OWNER`).
- **`GET /api/v1/recommendations/{id}`:** Rincian detail kriteria dan hasil armada terpilih terproteksi *Policy*.

---

## 6. Frontend User Interface (`/app/recommendations`)

- **Wizard Input Interaktif:** Formulir modular dengan validasi client-side ramah pengguna.
- **Hierarki Kartu Hasil:** Menampilkan badge peringkat (`#1 Pilihan Utama`), persentase skor, thumbnail foto armada, ringkasan spesifikasi, tarif awal per jam, dan kotak penjelasan alasan rekomendasi.
- **Tindakan Eksplisit:** Tautan langsung mengarahkan pengguna ke halaman detail katalog `/app/equipment/:id` untuk proses pemesanan mandiri.
- **Tab Riwayat:** Memungkinkan pelanggan meninjau ulang riwayat rekomendasi lampau.

---

## 7. Availability Integration (`EquipmentAvailabilityService`)

- Ketersediaan unit fisik dihitung langsung dari basis data tanpa asumsi kalender frontend.
- **Deteksi Bentrok Jadwal:** Unit yang sedang aktif dialokasikan pada pesanan `CONFIRMED`, `DISPATCHED`, `ONGOING` yang beririsan tanggal akan memotong kuota unit bebas.
- **Hard Exclusion:** Model armada dengan sisa ketersediaan 0 unit pada periode permintaan otomatis dieksklusi dari daftar hasil rekomendasi.
- **Eliminasi N+1:** Menggunakan kueri agregasi `GROUP BY` massal (*bulk query*).

---

## 8. Verifikasi Pengujian (Quality Gate Summary)

- **Backend Test Suite (PHPUnit):** **214 Passed (791 assertions), 0 Errors**.
- **Backend Code Style (Laravel Pint):** **100% Clean (PASSED)**.
- **Frontend Test Suite (Vitest):** **57 Passed (15 test files), 0 Errors**.
- **Frontend Build (Vite + TypeScript):** **100% Clean (Built in <1.2s)**.
- **10 Critical Business Scenarios:** Seluruh skenario bisnis kritis (ketersediaan, eksklusi bentrok jadwal, tie-breaking, proteksi data antar-user, zero side-effect booking) terverifikasi **PASSED**.

---

## 9. Keamanan & Performa (Security & Performance)

- **RBAC Policy:** `USER` terisolasi ketat hanya pada data miliknya sendiri. Akses ilegal menghasilkan `403 Forbidden`.
- **Input Sanitization:** Parameter numerik dan tanggal divalidasi ketat terhadap nilai negatif atau format salah.
- **Informasi Publik:** Nomor seri fisik mesin dan log mekanik bengkel tidak pernah terekspos ke respon pelanggan.
- **Optimasi Resource:** Gambar thumbnail menggunakan *native lazy loading*.

---

## 10. Known Limitations

- **Simulasi Ketersediaan Berbasis Hari:** Penghitungan bentrok jadwal saat ini berbasis tanggal kalender harian (belum mencakup granularitas per jam).
- **Rekomendasi Single Fleet/Model:** Output rekomendasi memberikan kandidat model terbaik secara independen (belum mengombinasikan paket *multi-fleet* misalnya 1 Excavator + 3 Dump Truck).

---

## 11. Rekomendasi untuk Phase 8 (Cart & Booking Engine)

1. **Integrasi Rekomendasi ke Keranjang (Cart):** Menyediakan tombol aksi dari halaman detail armada hasil rekomendasi untuk memasukkan unit langsung ke keranjang sewa pelanggan.
2. **Penguncian Unit & Checkout Validasi:** Menggunakan logika ketersediaan `EquipmentAvailabilityService` yang sudah teruji untuk validasi akhir sebelum pembayaran invoice *Down Payment (DP)*.
3. **Penyimpanan Snapshot Rekomendasi:** Menyimpan referensi `recommendation_request_id` opsional pada tabel `bookings` untuk analisis konversi keputusan sewa.

---

## 12. Final Status

```text
==================================================
PHASE 7 STATUS:
READY FOR PHASE 8
==================================================
- Backend Tests: 214 Passed (791 assertions)
- Frontend Tests: 57 Passed (15 test files)
- Code Style: Laravel Pint 100% Clean
- Frontend Build: TypeScript & Vite Clean
==================================================
```
