# Spesifikasi Rekomendasi Terintegrasi Ketersediaan (Phase 7E) - RAFA Rental System

Dokumentasi arsitektur penyaringan berbasis ketersediaan unit (*Availability-Aware Filtering*), logika deteksi bentrok jadwal (*overlap detection*), dan pengecualian armada pada mesin rekomendasi.

---

## 1. Prinsip Ketersediaan (Availability Principles)

Mesin rekomendasi RAFA Rental System menerapkan integrasi ketat dengan Domain `EquipmentUnit` dan `Booking`:
- **Single Source of Truth:** Selisih ketersediaan selalu dihitung secara *real-time* langsung dari tabel `equipment_units` dan `booking_unit_assignments` di database, bukan dari *cache* atau state *frontend*.
- **Hard Exclusion:** Model armada yang memiliki sisa kuota ketersediaan 0 unit pada periode yang direquest **tidak akan pernah** dimunculkan dalam hasil rekomendasi, tidak peduli seberapa tinggi skor kecocokan teknisnya.
- **Strictly Read-Only (No Reservation Side Effect):** Proses pengecekan ketersediaan bersifat *idempotent* dan *read-only*; tidak ada modifikasi status unit, pembentukan pesanan (*booking*), maupun penguncian unit (*soft booking*) selama kalkulasi.

---

## 2. Parameter Periode (Period Awareness)

Model *RecommendationCriteria* diperluas dengan kolom kalender operasional:
- `start_date` (DATE) - Tanggal mulai penyewaan.
- `end_date` (DATE) - Tanggal selesai penyewaan.
- `duration_days` (INTEGER) - Dapat berkolaborasi dengan `start_date` untuk menghitung `end_date` secara otomatis.

Jika pelanggan tidak mencantumkan parameter periode di formulir rekomendasi, maka ketersediaan dinilai secara *real-time* hanya berdasarkan status operasional unit saat ini (`EquipmentStatus::AVAILABLE`).

---

## 3. Logika Penghitungan Ketersediaan (`EquipmentAvailabilityService`)

Pengecekan ketersediaan diabstraksi secara terpusat pada layanan bersama (*shared service*) `EquipmentAvailabilityService` untuk menghindari logika redundan (*DRY Principle*) dan menghindari N+1 Queries:

**Formula Kapasitas Bebas:**
$$\text{Available Units} = \text{Total Operational Units} - \text{Booked Units}$$

1. **Total Operational Units:**
   Menghitung seluruh unit fisik aktif suatu model yang **tidak** berada dalam status `DECOMMISSIONED` (dihapus) atau `MAINTENANCE` (sedang dalam perbaikan bengkel).
2. **Booked Units:**
   Menghitung jumlah unit unik (*DISTINCT*) dari tabel *Booking Unit Assignments* yang aktif selama rentang `start_date` hingga `end_date`. Pemesanan dengan status `CANCELLED`, `REJECTED`, atau `EXPIRED` akan dieksklusi dari perhitungan beban *booking*.

**Query Bulk Optimization:** Layanan `getAvailabilityMap()` mengambil seluruh array `model_id` sekaligus dan memproses dua kueri agregasi (`GROUP BY`) untuk mengekstrak peta jumlah unit tersedia tanpa iterasi N+1.

---

## 4. Alur Integrasi pada Mesin Penilaian (Scoring Integration Flow)

Modifikasi pada `RecommendationScoringService::evaluate()` :

```text
1. Ambil List Seluruh EquipmentModel yang Aktif
       │
2. Resolusi Periode Rentang Waktu
       ├── Jika ada start_date & duration_days ─> Hitung end_date otomatis
       │
3. Pemanggilan Bulk EquipmentAvailabilityService::getAvailabilityMap
       │
4. Iterasi Model Armada
       ├── Cek kuota ketersediaan unit di Map (availableUnits > 0)
       ├── JIKA KOSONG (0 unit) ─> EXCLUDE (Continue loop, drop model)
       │
5. Kalkulasi Skor Kecocokan Teknis Berbobot (Weighted Score)
       │
6. Sisipkan [available_units] ke Result Breakdown
       │
7. Sorting & Filtering Sesuai Peringkat
       │
8. Kembalikan Koleksi Hasil (Max 5 Teratas)
```

---

## 5. Hasil Verifikasi Otomatis (`EquipmentAvailabilityServiceTest`)

- `test_get_availability_map_without_period_counts_currently_available_units`: **PASSED**
- `test_get_availability_map_with_period_subtracts_overlapping_bookings`: **PASSED** (Terverifikasi memotong kuota jika terjadi irisan/overlap waktu).
- `test_cancelled_or_rejected_bookings_do_not_reduce_availability`: **PASSED** (Pemesanan yang batal secara otomatis membebaskan kuota unit).
- `test_read_only_availability_does_not_mutate_equipment_status`: **PASSED** (Pengecekan dijamin tidak mengubah status data asli).
